<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\ImpactLevel;
use App\Models\Supply;
use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\TicketAttachmentService;
use App\Services\TicketHistoryService;
use App\Services\TicketInputService;
use App\Services\TicketLookupService;
use App\Services\TicketWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        private TicketInputService $input,
        private TicketWorkflowService $workflow,
        private TicketAttachmentService $attachments,
        private TicketHistoryService $history,
        private InventoryService $inventory,
        private TicketLookupService $lookups,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'priority' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'category' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'scope' => ['nullable', Rule::in(['all', 'mine', 'assigned', 'drafts', 'approvals'])],
        ]);

        $baseQuery = $this->visibleQuery($request->user());
        $metricsQuery = clone $baseQuery;
        $query = $baseQuery->with(['requester', 'category', 'subcategory', 'priority', 'status', 'currentGroup', 'currentAssignee'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(fn ($inner) => $inner->where('number', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%'));
            })
            ->when($filters['status'] ?? null, fn ($query, $id) => $query->where('ticket_status_id', $id))
            ->when($filters['priority'] ?? null, fn ($query, $id) => $query->where('ticket_priority_id', $id))
            ->when($filters['category'] ?? null, fn ($query, $id) => $query->where('ticket_category_id', $id));

        $scope = $filters['scope'] ?? 'all';
        $query->when($scope === 'mine', fn ($query) => $query->where('requester_id', $request->user()->id))
            ->when($scope === 'assigned', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('current_assignee_id', $request->user()->id)
                ->orWhereIn('current_it_group_id', $request->user()->technicalGroups()->pluck('it_groups.id'))))
            ->when($scope === 'drafts', fn ($query) => $query->where('is_draft', true))
            ->when($scope === 'approvals', fn ($query) => $query->whereHas('approvals', fn ($approval) => $approval->where('status', 'pending')));

        return view('tickets.index', [
            'tickets' => $query->orderByDesc('last_activity_at')->orderByDesc('created_at')->paginate(15)->withQueryString(),
            'metrics' => [
                'total' => (clone $metricsQuery)->count(),
                'open' => (clone $metricsQuery)->where('is_draft', false)->whereHas('status', fn ($q) => $q->where('is_final', false))->count(),
                'drafts' => (clone $metricsQuery)->where('is_draft', true)->count(),
                'urgent' => (clone $metricsQuery)->whereHas('priority', fn ($q) => $q->where('weight', '>=', 3))->whereHas('status', fn ($q) => $q->where('is_final', false))->count(),
            ],
            'statuses' => TicketStatus::where('is_active', true)->orderBy('sort_order')->get(),
            'priorities' => TicketPriority::where('is_active', true)->orderByDesc('weight')->get(),
            'categories' => TicketCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ticket::class);
        [$category, $subcategory] = $this->requestedClassification($request);

        return view('tickets.form', $this->formData(null, $category, $subcategory));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);
        $action = $request->validate(['action' => ['required', Rule::in(['draft', 'submit'])]])['action'];
        $submitting = $action === 'submit';
        $staffForm = $this->usesStaffForm();
        $internal = $request->boolean('is_internal');
        if ($internal) {
            $this->authorize('createInternal', Ticket::class);
        }
        $validated = $this->input->validate($request, $submitting, staffForm: $staffForm);
        $requester = $staffForm && ! empty($validated['requester_id'])
            ? User::where('status', 'active')->findOrFail($validated['requester_id']) : $request->user();

        $ticket = DB::transaction(function () use ($request, $validated, $requester, $internal, $submitting, $staffForm): Ticket {
            $ticket = Ticket::create(array_merge($this->input->ticketPayload($validated, $requester, $request->user(), $internal), [
                'number' => $this->workflow->nextNumber(), 'is_draft' => true,
                'ticket_status_id' => $this->workflow->initialStatus()->id,
                'approval_status' => 'not_required', 'last_activity_at' => now(),
            ]));
            $this->input->syncValues($ticket, $validated['dynamic_values']);
            $this->input->syncEquipment($ticket, $validated['equipment'], $request->user());
            $this->history->record($ticket, $request->user(), 'ticket_created', 'Ticket creado como borrador.', [], [
                'number' => $ticket->number, 'source' => $ticket->source, 'capture_mode' => $staffForm ? 'staff' : 'requester',
            ], $request);

            if ($submitting) {
                $this->workflow->submit($ticket, $request->user(), $request);
            }
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'initial');
            }

            return $ticket;
        });

        return redirect()->route('tickets.show', $ticket)->with('success', $submitting
            ? 'Ticket '.$ticket->number.' enviado correctamente.' : 'Borrador '.$ticket->number.' guardado.');
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->authorize('view', $ticket);
        $ticket->load([
            'requester.roles', 'creator', 'category', 'subcategory', 'form.fields', 'requesterArea', 'requesterDepartment',
            'impact', 'urgency', 'priority', 'status', 'currentArea', 'currentGroup', 'currentAssignee', 'solutionBy', 'closedBy',
            'fieldValues.field', 'equipmentLinks.equipment', 'supplyUsages.supply', 'supplyUsages.recordedBy',
            'approvals.approver', 'approvals.approverRole', 'approvals.decidedBy',
            'assignments.fromArea', 'assignments.fromGroup', 'assignments.fromUser', 'assignments.toArea',
            'assignments.toGroup', 'assignments.toUser', 'assignments.assignedBy', 'review.reviewedBy',
            'escalations.escalatedTo', 'escalations.createdBy',
            'thirdPartyDependencies.createdBy', 'thirdPartyDependencies.resolvedBy',
            'maintenanceOrder.plan', 'maintenanceOrder.equipment',
        ]);

        $canSeeInternal = $request->user()->hasPermission('tickets.internal_note') || $request->user()->hasPermission('tickets.view_all');
        $comments = $ticket->comments()->with(['user.roles', 'attachments'])
            ->when(! $canSeeInternal, fn ($query) => $query->where('is_internal', false))->get();
        $activities = $canSeeInternal ? $ticket->activities()->with(['user', 'attachments'])->get() : collect();
        $visibleAttachments = $ticket->attachments()->with('uploadedBy')
            ->when(! $canSeeInternal, fn ($query) => $query->where('is_internal', false))->get();
        $histories = $ticket->histories()->with('user')->limit(100)->get();
        $relations = $ticket->outgoingRelations()->with('relatedTicket.status')->get()
            ->merge($ticket->incomingRelations()->with('ticket.status')->get());

        return view('tickets.show', [
            'ticket' => $ticket, 'comments' => $comments, 'activities' => $activities,
            'attachments' => $visibleAttachments, 'histories' => $histories, 'relations' => $relations,
            'canSeeInternal' => $canSeeInternal,
            'availableStatuses' => TicketStatus::where('is_active', true)->where('is_final', false)
                ->whereNotIn('slug', ['registrado', 'pendiente-aprobacion', 'solucionado'])->orderBy('sort_order')->get(),
            'itAreas' => Area::where('type', 'it')->where('is_active', true)->orderBy('name')->get(),
            'groups' => TechnicalGroup::with('area')->where('is_active', true)->orderBy('name')->get(),
            'technicians' => User::with(['roles', 'technicalGroups'])->where('status', 'active')->whereHas('roles', fn ($query) => $query
                ->where('is_active', true)->whereIn('slug', ['jefatura-it', 'encargado-area-it', 'tecnico-it', 'programador-it']))->orderBy('name')->get(),
            'selectedRelatedTickets' => ctype_digit((string) old('related_ticket_id', ''))
                ? $this->visibleQuery($request->user())->whereKey(old('related_ticket_id'))->whereKeyNot($ticket->id)
                    ->where('is_draft', false)->get(['id', 'number', 'title'])
                    ->map(fn ($candidate) => $this->relatedOption($candidate))->all() : [],
            'selectedSupplies' => $request->user()->can('reserveSupply', $ticket) && ctype_digit((string) old('supply_id', ''))
                ? Supply::where('is_active', true)->whereKey(old('supply_id'))->get()
                    ->map(fn ($supply) => app(TicketLookupService::class)->supplyOption($supply, $ticket))->all()
                : [],
        ]);
    }

    public function edit(Request $request, Ticket $ticket): View
    {
        $this->authorize('update', $ticket);
        $ticket->load(['fieldValues', 'equipmentLinks', 'attachments']);

        return view('tickets.form', $this->formData($ticket, $ticket->category, $ticket->subcategory));
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $action = $request->validate(['action' => ['required', Rule::in(['draft', 'submit'])]])['action'];
        $validated = $this->input->validate($request, $action === 'submit', $ticket, $this->usesStaffForm($ticket));
        if ((int) ($validated['lock_version'] ?? 0) !== $ticket->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'El ticket fue actualizado en otra sesión. Recarga la página antes de guardar.']);
        }

        DB::transaction(function () use ($request, $ticket, $validated, $action): void {
            $from = $ticket->only(['title', 'ticket_category_id', 'ticket_subcategory_id', 'impact_level_id', 'urgency_level_id']);
            $payload = $this->input->ticketPayload($validated, $ticket->requester, $ticket->creator, $ticket->source === 'it_internal');
            $ticket->update(array_merge($payload, [
                'lock_version' => $ticket->lock_version + 1, 'last_activity_at' => now(),
            ]));
            $this->input->syncValues($ticket, $validated['dynamic_values']);
            $this->input->syncEquipment($ticket, $validated['equipment'], $request->user());
            $this->history->record($ticket, $request->user(), 'ticket_updated', 'Información del ticket actualizada.', $from,
                $ticket->only(array_keys($from)), $request);

            if ($action === 'submit') {
                $this->workflow->submit($ticket, $request->user(), $request);
            }
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'initial');
            }
        });

        return redirect()->route('tickets.show', $ticket)->with('success', $action === 'submit'
            ? 'Ticket enviado nuevamente para atención.' : 'Borrador actualizado.');
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('changeStatus', $ticket);
        $validated = $request->validate([
            'ticket_status_id' => ['required', 'integer', Rule::exists('ticket_statuses', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->workflow->changeStatus($ticket, TicketStatus::findOrFail($validated['ticket_status_id']), $request->user(), $validated['reason'], $request);

        return back()->with('success', 'Estado del ticket actualizado.');
    }

    public function cancel(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('cancel', $ticket);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $from = $ticket->status?->name;
        DB::transaction(function () use ($request, $ticket, $validated, $from): void {
            $this->inventory->releaseTicketReservations($ticket, $request->user());
            $ticket->update([
                'ticket_status_id' => $this->workflow->status('cancelado')->id,
                'cancellation_reason' => $validated['reason'], 'cancelled_at' => now(),
                'is_draft' => false, 'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), 'ticket_cancelled', 'Ticket cancelado con justificación.',
                ['status' => $from], ['status' => 'Cancelado', 'reason' => $validated['reason']], $request);
        });

        return back()->with('success', 'Ticket cancelado.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reopen', $ticket);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1500'],
            'stock_action' => ['nullable', Rule::in(['keep', 'return'])]]);
        $stockAction = $validated['stock_action'] ?? 'keep';
        abort_if($stockAction === 'return' && ! $request->user()->hasPermission('inventory.consume'), 403);
        DB::transaction(function () use ($request, $ticket, $validated, $stockAction): void {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->authorize('reopen', $ticket);
            $previousSolution = ['solution' => $ticket->solution, 'solution_by_id' => $ticket->solution_by_id,
                'solved_at' => $ticket->solved_at?->toIso8601String()];
            $returned = $stockAction === 'return'
                ? $this->inventory->returnConsumedForTicket($ticket, $request->user(), $validated['reason']) : 0;
            $ticket->update([
                'ticket_status_id' => $this->workflow->status('en-proceso')->id,
                'closed_at' => null, 'closed_by_id' => null, 'reopened_at' => now(),
                'solved_at' => null,
                'reopen_count' => $ticket->reopen_count + 1, 'last_activity_at' => now(),
                'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), 'ticket_reopened', 'Ticket reabierto para continuar la atención.',
                ['status' => 'Cerrado', ...$previousSolution], ['status' => 'En proceso', 'reason' => $validated['reason'],
                    'stock_action' => $stockAction, 'returned_supplies' => $returned], $request);
        });

        return redirect()->to(route('tickets.show', $ticket).'#ticket-resolution')
            ->with('edit_solution', true)->with('success', 'Ticket reabierto en atención. Actualiza la solución y revisa las reservas de insumos antes del nuevo cierre.');
    }

    public function relatedLookup(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('relate', $ticket);
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($validated['q'] ?? '');
        $matches = $this->visibleQuery($request->user())->whereKeyNot($ticket->id)->where('is_draft', false)
            ->when($term !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('number', 'like', '%'.$term.'%')->orWhere('title', 'like', '%'.$term.'%')))
            ->orderByDesc('id')->limit(21)->get(['id', 'number', 'title']);

        return response()->json(['results' => $matches->take(20)->map(fn ($candidate) => $this->relatedOption($candidate))->values(),
            'has_more' => $matches->count() > 20]);
    }

    private function relatedOption(Ticket $ticket): array
    {
        return ['value' => (string) $ticket->id, 'label' => $ticket->number, 'description' => $ticket->title ?: 'Sin asunto'];
    }

    private function visibleQuery(User $user): Builder
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

    private function requestedClassification(Request $request): array
    {
        $categoryId = $request->integer('category_id');
        $subcategoryId = $request->integer('subcategory_id');
        $category = $categoryId ? TicketCategory::where('is_active', true)->findOrFail($categoryId) : null;
        $subcategory = $subcategoryId ? TicketSubcategory::where('is_active', true)->findOrFail($subcategoryId) : null;
        if ($subcategory && (! $category || $subcategory->ticket_category_id !== $category->id)) {
            abort(404);
        }

        return [$category, $subcategory];
    }

    private function formData(?Ticket $ticket, ?TicketCategory $category, ?TicketSubcategory $subcategory): array
    {
        // Los borradores existentes conservan su origen y sus respuestas.
        $staffCapture = $this->usesStaffForm($ticket);
        $staffForm = $staffCapture && Gate::allows('createInternal', Ticket::class);
        $form = $category && ! $staffCapture ? $this->input->matchingForm($category->id, $subcategory?->id) : null;
        $specificFields = $form?->fields->where('is_visible', true)
            ->filter(fn ($field) => ! $this->input->baseFieldName($field) && $field->type !== 'file') ?? collect();
        $requesterId = old('requester_id', auth()->id());
        if (! is_scalar($requesterId) || ! ctype_digit((string) $requesterId)) {
            $requesterId = auth()->id();
        }
        $formRequester = $ticket?->requester ?? ($staffForm
            ? User::where('status', 'active')->find($requesterId) : auth()->user());
        $formRequester ??= auth()->user();
        $hasOldSelection = session()->hasOldInput('equipment_selection_present');
        $cleanIds = fn ($values) => collect(is_array($values) ? $values : [])
            ->filter(fn ($value) => is_scalar($value) && ctype_digit((string) $value))->map(fn ($value) => (int) $value)->unique()->take(10);
        $equipmentIds = $cleanIds(old('equipment_ids', $hasOldSelection ? [] : ($ticket?->equipmentLinks->pluck('equipment_id')->filter()->all() ?? [])));
        $legacyIds = $cleanIds(old('legacy_equipment_link_ids', $hasOldSelection ? [] : ($ticket?->equipmentLinks->whereNull('equipment_id')->pluck('id')->all() ?? [])));
        $equipmentSelection = Equipment::whereIn('id', $equipmentIds)->orderBy('inventory_code')->get()
            ->map(fn ($asset) => $this->lookups->equipmentOption($asset));
        foreach ($ticket?->equipmentLinks->whereNull('equipment_id')->whereIn('id', $legacyIds) ?? [] as $link) {
            $equipmentSelection->push([
                'value' => 'legacy:'.$link->id, 'input_value' => (string) $link->id, 'input_name' => 'legacy_equipment_link_ids[]',
                'label' => $link->inventory_code.' · '.($link->equipment_name ?: 'Referencia anterior'),
                'description' => 'Referencia anterior conservada; sin equipo registrado asociado.',
            ]);
        }

        return [
            'ticket' => $ticket, 'selectedCategory' => $category, 'selectedSubcategory' => $subcategory, 'form' => $form,
            'staffForm' => $staffForm, 'specificFields' => $specificFields,
            'formRequester' => $formRequester,
            'requesterSelection' => [$this->lookups->requesterOption($formRequester)],
            'equipmentSelection' => $equipmentSelection->values()->all(),
            'fileFields' => $form?->fields->where('is_visible', true)->where('type', 'file') ?? collect(),
            'categories' => TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'areas' => Area::with(['departments' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)->orderBy('name')->get(),
            'impacts' => ImpactLevel::where('is_active', true)->orderBy('value')->get(),
            'urgencies' => UrgencyLevel::where('is_active', true)->orderBy('value')->get(),
            'attentionTypes' => Ticket::ATTENTION_TYPES,
            'existingValues' => $ticket?->fieldValues?->keyBy('form_field_id') ?? collect(),
        ];
    }

    private function usesStaffForm(?Ticket $ticket = null): bool
    {
        if (! $ticket) {
            return Gate::allows('createInternal', Ticket::class);
        }

        $created = $ticket->histories()->where('event', 'ticket_created')->oldest('id')->first();
        if (isset($created?->to_values['capture_mode'])) {
            return $created->to_values['capture_mode'] === 'staff';
        }

        return $ticket->source === 'it_internal'
            || ($ticket->ticket_form_id === null && $ticket->creator && Gate::forUser($ticket->creator)->allows('createInternal', Ticket::class));
    }
}
