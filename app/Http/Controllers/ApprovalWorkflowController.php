<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Area;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketSubcategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): View
    {
        return view('configuration.approvals.index', [
            'workflows' => ApprovalWorkflow::with(['category', 'subcategory', 'requesterArea', 'minimumPriority', 'levels.approver', 'levels.approverRole'])
                ->orderByDesc('is_active')->orderBy('name')->get(),
            'delegations' => ApprovalDelegation::with(['delegator', 'delegate', 'creator'])->latest('starts_at')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
            'editingDelegation' => $request->integer('edit_delegation')
                ? ApprovalDelegation::findOrFail($request->integer('edit_delegation')) : null,
        ]);
    }

    public function create(): View
    {
        return view('configuration.approvals.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateWorkflow($request);
        $workflow = ApprovalWorkflow::create($this->workflowPayload($request, $validated));
        $this->audit($request, 'approval_workflow_created', $workflow->id, $workflow->name);

        return redirect()->route('configuration.approvals.edit', $workflow)
            ->with('success', 'Regla de aprobación creada. Agrega sus niveles de autorización.');
    }

    public function edit(ApprovalWorkflow $workflow): View
    {
        $workflow->load(['levels.approver', 'levels.approverRole']);

        return view('configuration.approvals.form', $this->formData($workflow));
    }

    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $validated = $this->validateWorkflow($request, $workflow);
        $workflow->update($this->workflowPayload($request, $validated));
        $this->audit($request, 'approval_workflow_updated', $workflow->id, $workflow->name);

        return redirect()->route('configuration.approvals.edit', $workflow)->with('success', 'Regla de aprobación actualizada.');
    }

    public function storeLevel(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $validated = $this->validateLevel($request, $workflow);
        $level = $workflow->levels()->create($this->levelPayload($request, $validated));
        $this->audit($request, 'approval_level_created', $level->id, $level->name, ['workflow_id' => $workflow->id]);

        return redirect()->route('configuration.approvals.edit', $workflow)->with('success', 'Nivel de aprobación agregado.');
    }

    public function updateLevel(Request $request, ApprovalWorkflow $workflow, ApprovalLevel $level): RedirectResponse
    {
        abort_unless($level->approval_workflow_id === $workflow->id, 404);
        $validated = $this->validateLevel($request, $workflow, $level);
        $level->update($this->levelPayload($request, $validated));
        $this->audit($request, 'approval_level_updated', $level->id, $level->name, ['workflow_id' => $workflow->id]);

        return redirect()->route('configuration.approvals.edit', $workflow)->with('success', 'Nivel de aprobación actualizado.');
    }

    public function storeDelegation(Request $request): RedirectResponse
    {
        $validated = $this->validateDelegation($request);
        $delegation = ApprovalDelegation::create($this->delegationPayload($request, $validated));
        $this->audit($request, 'approval_delegation_created', $delegation->id, $delegation->reason);

        return redirect(route('configuration.approvals.index').'#delegaciones')->with('success', 'Suplencia temporal registrada.');
    }

    public function updateDelegation(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        $validated = $this->validateDelegation($request, $delegation);
        $delegation->update($this->delegationPayload($request, $validated, $delegation));
        $this->audit($request, 'approval_delegation_updated', $delegation->id, $delegation->reason);

        return redirect(route('configuration.approvals.index').'#delegaciones')->with('success', 'Suplencia temporal actualizada.');
    }

    private function validateWorkflow(Request $request, ?ApprovalWorkflow $workflow = null): array
    {
        $validated = $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'ticket_subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'requester_area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'minimum_priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('approval_workflows')->ignore($workflow?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->ensureSubcategoryBelongsToCategory($validated);

        return $validated;
    }

    private function validateLevel(Request $request, ApprovalWorkflow $workflow, ?ApprovalLevel $level = null): array
    {
        $validated = $request->validate([
            'level_number' => [
                'required', 'integer', 'between:1,20',
                Rule::unique('approval_levels')->where(fn ($query) => $query->where('approval_workflow_id', $workflow->id))->ignore($level?->id),
            ],
            'name' => ['required', 'string', 'max:140'],
            'approver_type' => ['required', Rule::in(array_keys(ApprovalLevel::APPROVER_TYPES))],
            'approver_user_id' => [Rule::requiredIf($request->input('approver_type') === 'specific_user'), 'nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'approver_role_id' => [Rule::requiredIf($request->input('approver_type') === 'role'), 'nullable', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'is_required' => ['nullable', 'boolean'],
            'allow_delegation' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $validated;
    }

    private function validateDelegation(Request $request, ?ApprovalDelegation $delegation = null): array
    {
        $validated = $request->validate([
            'delegator_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'delegate_user_id' => ['required', 'integer', 'different:delegator_user_id', Rule::exists('users', 'id')->where('status', 'active')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_active')) {
            $overlap = ApprovalDelegation::where('delegator_user_id', $validated['delegator_user_id'])
                ->where('is_active', true)
                ->when($delegation, fn ($query) => $query->whereKeyNot($delegation->id))
                ->where('starts_at', '<', Carbon::parse($validated['ends_at']))
                ->where('ends_at', '>', Carbon::parse($validated['starts_at']))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'El titular ya tiene una suplencia activa en ese período.']);
            }
        }

        return $validated;
    }

    private function workflowPayload(Request $request, array $validated): array
    {
        return [
            'ticket_category_id' => $validated['ticket_category_id'],
            'ticket_subcategory_id' => $validated['ticket_subcategory_id'] ?? null,
            'requester_area_id' => $validated['requester_area_id'] ?? null,
            'minimum_priority_id' => $validated['minimum_priority_id'] ?? null,
            'code' => Str::upper(trim($validated['code'])), 'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null, 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function levelPayload(Request $request, array $validated): array
    {
        return [
            'level_number' => $validated['level_number'], 'name' => trim($validated['name']),
            'approver_type' => $validated['approver_type'],
            'approver_user_id' => $validated['approver_type'] === 'specific_user' ? $validated['approver_user_id'] : null,
            'approver_role_id' => $validated['approver_type'] === 'role' ? $validated['approver_role_id'] : null,
            'is_required' => $request->boolean('is_required'), 'allow_delegation' => $request->boolean('allow_delegation'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function delegationPayload(Request $request, array $validated, ?ApprovalDelegation $delegation = null): array
    {
        return [
            'delegator_user_id' => $validated['delegator_user_id'], 'delegate_user_id' => $validated['delegate_user_id'],
            'starts_at' => Carbon::parse($validated['starts_at']), 'ends_at' => Carbon::parse($validated['ends_at']),
            'reason' => trim($validated['reason']), 'is_active' => $request->boolean('is_active'),
            'created_by' => $delegation?->created_by ?? $request->user()->id,
        ];
    }

    private function ensureSubcategoryBelongsToCategory(array $validated): void
    {
        if (! empty($validated['ticket_subcategory_id']) && ! TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $validated['ticket_category_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_subcategory_id' => 'La subcategoría no pertenece a la categoría seleccionada.']);
        }
    }

    private function formData(?ApprovalWorkflow $workflow = null): array
    {
        return [
            'workflow' => $workflow,
            'categories' => TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'priorities' => TicketPriority::where('is_active', true)->orderByDesc('weight')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'approverTypes' => ApprovalLevel::APPROVER_TYPES,
        ];
    }

    private function audit(Request $request, string $event, int $recordId, string $recordName, array $extra = []): void
    {
        SecurityEvent::record($event, $request, $request->user(), array_merge([
            'record_id' => $recordId, 'record_name' => $recordName, 'performed_by' => $request->user()->id,
        ], $extra));
    }
}
