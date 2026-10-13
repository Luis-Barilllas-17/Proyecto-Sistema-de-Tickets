<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\Area;
use App\Models\Department;
use App\Models\DevelopmentRequest;
use App\Models\DevelopmentSecurityControl;
use App\Models\ImpactLevel;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Services\DevelopmentRequestService;
use App\Services\TicketAttachmentService;
use App\Services\TicketHistoryService;
use App\Services\TicketWorkflowService;
use App\Support\UploadSecurityPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DevelopmentController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketWorkflowService $workflow,
        private TicketAttachmentService $attachments,
        private TicketHistoryService $history,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DevelopmentRequest::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(DevelopmentRequest::REQUEST_TYPES))],
            'stage' => ['nullable', Rule::in(array_keys(DevelopmentRequest::LIFECYCLE_STATUSES))],
            'scope' => ['nullable', Rule::in(['all', 'mine', 'deployment'])],
        ]);

        $visibleTickets = $this->visibleTicketQuery($request->user());
        $base = DevelopmentRequest::query()->whereHas('ticket', fn ($query) => $query->whereIn('id', (clone $visibleTickets)->select('id')));
        $metricsBase = clone $base;
        $query = $base->with(['ticket.requester', 'ticket.status', 'ticket.priority', 'ticket.currentGroup'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('project_name', 'like', '%'.$search.'%')
                ->orWhereHas('ticket', fn ($ticket) => $ticket->where('number', 'like', '%'.$search.'%'))))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('request_type', $type))
            ->when($filters['stage'] ?? null, fn ($query, $stage) => $query->where('lifecycle_status', $stage));

        $scope = $filters['scope'] ?? 'all';
        $query->when($scope === 'mine', fn ($query) => $query->whereHas('ticket', fn ($ticket) => $ticket->where('requester_id', $request->user()->id)))
            ->when($scope === 'deployment', fn ($query) => $query->whereHas('deploymentAuthorizations', fn ($authorization) => $authorization->where('status', 'pending')));

        return view('development.index', [
            'developmentRequests' => $query->latest('updated_at')->paginate(12)->withQueryString(),
            'metrics' => [
                'total' => (clone $metricsBase)->count(),
                'development' => (clone $metricsBase)->whereIn('lifecycle_status', ['analysis', 'definition', 'development'])->count(),
                'testing' => (clone $metricsBase)->where('lifecycle_status', 'testing')->count(),
                'deployment' => (clone $metricsBase)->whereHas('deploymentAuthorizations', fn ($query) => $query->where('status', 'pending'))->count(),
            ],
            'filters' => $filters,
            'requestTypes' => DevelopmentRequest::REQUEST_TYPES,
            'stages' => DevelopmentRequest::LIFECYCLE_STATUSES,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', DevelopmentRequest::class);

        return view('development.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DevelopmentRequest::class);
        [$validated, $submitting] = $this->validatedRequest($request);
        $category = TicketCategory::where('code', 'DEV')->where('is_active', true)->firstOrFail();
        $subcategory = TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $category->id)->where('is_active', true)->firstOrFail();

        $developmentRequest = DB::transaction(function () use ($request, $validated, $submitting, $category, $subcategory): DevelopmentRequest {
            $user = $request->user();
            $ticket = Ticket::create([
                'number' => $this->workflow->nextNumber(),
                'requester_id' => $user->id,
                'created_by_id' => $user->id,
                'source' => 'requester',
                'is_draft' => true,
                'title' => $this->development->clean($validated['project_name'] ?? null),
                'description' => $this->development->clean($validated['business_need'] ?? null),
                'ticket_category_id' => $category->id,
                'ticket_subcategory_id' => $subcategory->id,
                'ticket_form_id' => TicketForm::where('code', 'FRM-DEV-REQ')->value('id'),
                'requester_area_id' => $user->area_id ?: ($validated['requester_area_id'] ?? null),
                'requester_department_id' => $user->department_id ?: ($validated['requester_department_id'] ?? null),
                'location' => $this->development->clean($validated['location'] ?? null),
                'attention_type' => 'development',
                'impact_level_id' => $validated['impact_level_id'] ?? null,
                'urgency_level_id' => $validated['urgency_level_id'] ?? null,
                'ticket_status_id' => $this->workflow->initialStatus()->id,
                'approval_status' => 'not_required',
                'last_activity_at' => now(),
            ]);
            $developmentRequest = $ticket->developmentRequest()->create($this->developmentPayload($validated));
            $this->development->initializeSecurityControls($developmentRequest, $validated['security_controls'] ?? []);
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $user, $request->file('attachments'), 'development_request');
            }
            $this->history->record($ticket, $user, 'development_request_created', 'Solicitud especializada de desarrollo registrada.', [], [
                'request_type' => $developmentRequest->request_type,
                'classification' => $developmentRequest->information_classification,
            ], $request);
            if ($submitting) {
                $this->workflow->submit($ticket, $user, $request);
            }

            return $developmentRequest;
        });

        return redirect()->route('development.show', $developmentRequest)->with('success', $submitting
            ? 'Solicitud de desarrollo enviada a aprobación.' : 'Borrador de desarrollo guardado.');
    }

    public function show(Request $request, DevelopmentRequest $developmentRequest): View
    {
        $this->authorize('view', $developmentRequest);
        $developmentRequest->load([
            'ticket.requester.roles', 'ticket.creator', 'ticket.category', 'ticket.subcategory', 'ticket.status',
            'ticket.priority', 'ticket.currentGroup', 'ticket.currentAssignee', 'ticket.approvals.approver',
            'ticket.approvals.approverRole', 'ticket.attachments.uploadedBy', 'ticket.histories.user',
            'requirements.createdBy', 'requirements.updatedBy', 'securityControls.verifiedBy',
            'accessProfiles.createdBy', 'testEvidences.executedBy', 'testEvidences.attachments',
            'deploymentAuthorizations.requestedBy', 'deploymentAuthorizations.decidedBy',
        ]);

        return view('development.show', [
            'developmentRequest' => $developmentRequest,
            'ticket' => $developmentRequest->ticket,
            'readinessIssues' => $this->development->deploymentReadiness($developmentRequest),
        ]);
    }

    public function edit(DevelopmentRequest $developmentRequest): View
    {
        $this->authorize('update', $developmentRequest);
        $developmentRequest->load('ticket');

        return view('development.form', $this->formData($developmentRequest));
    }

    public function update(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('update', $developmentRequest);
        [$validated, $submitting] = $this->validatedRequest($request, $developmentRequest);
        $ticket = $developmentRequest->ticket;
        $category = TicketCategory::where('code', 'DEV')->where('is_active', true)->firstOrFail();
        $subcategory = TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $category->id)->where('is_active', true)->firstOrFail();
        if ((int) ($validated['lock_version'] ?? 0) !== $ticket->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'La solicitud cambió en otra sesión. Recarga la página antes de guardar.']);
        }

        DB::transaction(function () use ($request, $developmentRequest, $ticket, $validated, $submitting, $subcategory): void {
            $before = $developmentRequest->only(['request_type', 'project_name', 'information_classification', 'lifecycle_status']);
            $ticket->update([
                'title' => $this->development->clean($validated['project_name'] ?? null),
                'description' => $this->development->clean($validated['business_need'] ?? null),
                'ticket_subcategory_id' => $subcategory->id,
                'requester_area_id' => $request->user()->area_id ?: ($validated['requester_area_id'] ?? null),
                'requester_department_id' => $request->user()->department_id ?: ($validated['requester_department_id'] ?? null),
                'location' => $this->development->clean($validated['location'] ?? null),
                'impact_level_id' => $validated['impact_level_id'] ?? null,
                'urgency_level_id' => $validated['urgency_level_id'] ?? null,
                'last_activity_at' => now(),
                'lock_version' => $ticket->lock_version + 1,
            ]);
            $developmentRequest->update($this->developmentPayload($validated));
            $this->development->initializeSecurityControls($developmentRequest, $validated['security_controls'] ?? []);
            foreach ($developmentRequest->securityControls as $control) {
                $required = in_array($control->control_type, $validated['security_controls'] ?? [], true);
                $updates = ['is_required' => $required];
                if ($required && $control->status === 'not_applicable') {
                    $updates['status'] = 'planned';
                } elseif (! $required && $control->status === 'planned') {
                    $updates['status'] = 'not_applicable';
                }
                $control->update($updates);
            }
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'development_request');
            }
            $this->history->record($ticket, $request->user(), 'development_request_updated', 'Información de la solicitud de desarrollo actualizada.',
                $before, $developmentRequest->fresh()->only(array_keys($before)), $request);
            if ($submitting && $ticket->is_draft) {
                $this->workflow->submit($ticket, $request->user(), $request);
            }
        });

        return redirect()->route('development.show', $developmentRequest)->with('success', $submitting
            ? 'Solicitud actualizada y enviada.' : 'Solicitud de desarrollo actualizada.');
    }

    private function validatedRequest(Request $request, ?DevelopmentRequest $developmentRequest = null): array
    {
        $action = $request->validate(['action' => ['required', Rule::in(['draft', 'submit', 'save'])]])['action'];
        $submitting = $action === 'submit';
        $complete = $submitting || ($developmentRequest && ! $developmentRequest->ticket->is_draft);
        $required = $complete ? 'required' : 'nullable';
        $validated = $request->validate([
            'action' => ['required', Rule::in(['draft', 'submit', 'save'])],
            'request_type' => ['required', Rule::in(array_keys(DevelopmentRequest::REQUEST_TYPES))],
            'ticket_subcategory_id' => ['required', 'integer', 'exists:ticket_subcategories,id'],
            'project_name' => [$required, 'string', 'max:200'],
            'business_need' => [$required, 'string', 'max:15000'],
            'objective' => [$required, 'string', 'max:15000'],
            'scope' => [$required, 'string', 'max:20000'],
            'expected_benefits' => ['nullable', 'string', 'max:5000'],
            'affected_system' => ['nullable', 'string', 'max:200'],
            'expected_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'technical_restrictions' => ['nullable', 'string', 'max:10000'],
            'information_classification' => [$required, Rule::in(array_keys(DevelopmentRequest::INFORMATION_CLASSIFICATIONS))],
            'contains_sensitive_data' => ['nullable', 'boolean'],
            'sensitive_data_description' => ['nullable', 'string', 'max:5000'],
            'involves_third_parties' => ['nullable', 'boolean'],
            'third_party_details' => ['nullable', 'string', 'max:5000'],
            'access_overview' => ['nullable', 'string', 'max:5000'],
            'repository_reference' => ['nullable', 'string', 'max:255'],
            'requester_area_id' => [$required, 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'requester_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'location' => [$required, 'string', 'max:255'],
            'impact_level_id' => [$required, 'integer', Rule::exists('impact_levels', 'id')->where('is_active', true)],
            'urgency_level_id' => [$required, 'integer', Rule::exists('urgency_levels', 'id')->where('is_active', true)],
            'security_controls' => ['nullable', 'array', 'max:6'],
            'security_controls.*' => [Rule::in(array_keys(DevelopmentSecurityControl::TYPES))],
            'attachments' => ['nullable', 'array', 'max:'.UploadSecurityPolicy::MAX_FILES_PER_REQUEST],
            'attachments.*' => UploadSecurityPolicy::fileRules(TicketAttachmentService::ALLOWED_EXTENSIONS),
            'lock_version' => [$developmentRequest ? 'required' : 'nullable', 'integer', 'min:1'],
        ]);

        if ($complete && $request->boolean('contains_sensitive_data') && blank($validated['sensitive_data_description'] ?? null)) {
            throw ValidationException::withMessages(['sensitive_data_description' => 'Describe los datos sensibles que procesará la solución.']);
        }
        if ($complete && $request->boolean('involves_third_parties') && blank($validated['third_party_details'] ?? null)) {
            throw ValidationException::withMessages(['third_party_details' => 'Identifica a los terceros y su participación.']);
        }
        if (! empty($validated['requester_department_id']) && (empty($validated['requester_area_id']) || ! Department::whereKey($validated['requester_department_id'])
            ->where('area_id', $validated['requester_area_id'])->exists())) {
            throw ValidationException::withMessages(['requester_department_id' => 'El departamento no pertenece al área seleccionada.']);
        }

        $validated['contains_sensitive_data'] = $request->boolean('contains_sensitive_data');
        $validated['involves_third_parties'] = $request->boolean('involves_third_parties');

        return [$validated, $submitting];
    }

    private function developmentPayload(array $validated): array
    {
        return [
            'request_type' => $validated['request_type'],
            'project_name' => $this->development->clean($validated['project_name'] ?? null),
            'business_need' => $this->development->clean($validated['business_need'] ?? null),
            'objective' => $this->development->clean($validated['objective'] ?? null),
            'scope' => $this->development->clean($validated['scope'] ?? null),
            'expected_benefits' => $this->development->clean($validated['expected_benefits'] ?? null),
            'affected_system' => $this->development->clean($validated['affected_system'] ?? null),
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
            'technical_restrictions' => $this->development->clean($validated['technical_restrictions'] ?? null),
            'information_classification' => $validated['information_classification'] ?? 'internal',
            'contains_sensitive_data' => $validated['contains_sensitive_data'],
            'sensitive_data_description' => $this->development->clean($validated['sensitive_data_description'] ?? null),
            'involves_third_parties' => $validated['involves_third_parties'],
            'third_party_details' => $this->development->clean($validated['third_party_details'] ?? null),
            'access_overview' => $this->development->clean($validated['access_overview'] ?? null),
            'repository_reference' => $this->development->clean($validated['repository_reference'] ?? null),
        ];
    }

    private function formData(?DevelopmentRequest $developmentRequest = null): array
    {
        $category = TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('code', 'DEV')->where('is_active', true)->firstOrFail();

        return [
            'developmentRequest' => $developmentRequest,
            'ticket' => $developmentRequest?->ticket,
            'category' => $category,
            'areas' => Area::with(['departments' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)->orderBy('name')->get(),
            'impacts' => ImpactLevel::where('is_active', true)->orderBy('value')->get(),
            'urgencies' => UrgencyLevel::where('is_active', true)->orderBy('value')->get(),
        ];
    }

    private function visibleTicketQuery(User $user): Builder
    {
        $query = Ticket::query();
        if ($user->isSuperAdmin() || $user->hasPermission('tickets.view_all')) {
            return $query;
        }

        $groupIds = $user->hasPermission('tickets.view_assigned') ? $user->technicalGroups()->pluck('it_groups.id') : collect();
        $roleIds = $user->roles()->where('is_active', true)->pluck('roles.id');
        $delegators = ApprovalDelegation::where('delegate_user_id', $user->id)->where('is_active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>=', now())->pluck('delegator_user_id');

        return $query->where(function (Builder $query) use ($user, $groupIds, $roleIds, $delegators): void {
            $query->where('requester_id', $user->id)->orWhere('created_by_id', $user->id)
                ->when($user->hasPermission('tickets.view_assigned'), fn ($inner) => $inner
                    ->orWhere('current_assignee_id', $user->id)->orWhereIn('current_it_group_id', $groupIds))
                ->orWhereHas('approvals', fn ($approval) => $approval->where('status', 'pending')->where(function ($eligible) use ($user, $roleIds, $delegators): void {
                    $eligible->where('approver_user_id', $user->id)->orWhereIn('approver_role_id', $roleIds)
                        ->orWhere(fn ($delegated) => $delegated->whereIn('approver_user_id', $delegators)
                            ->whereHas('level', fn ($level) => $level->where('allow_delegation', true)));
                }));
        });
    }
}
