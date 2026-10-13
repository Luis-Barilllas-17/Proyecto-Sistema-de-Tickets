<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentRequirement;
use App\Services\DevelopmentRequestService;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DevelopmentRequirementController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketHistoryService $history,
    ) {}

    public function store(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('manageRequirements', $developmentRequest);
        $validated = $this->validatedRequirement($request, $developmentRequest);

        DB::transaction(function () use ($request, $developmentRequest, $validated): void {
            $code = ($validated['code'] ?? null) ?: $this->nextCode($developmentRequest);
            $requirement = $developmentRequest->requirements()->create($this->payload($validated) + [
                'code' => $code,
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);
            $developmentRequest->update(['lifecycle_status' => 'definition']);
            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'development_requirement_created',
                'Requerimiento '.$requirement->code.' registrado.', [], ['type' => $requirement->type, 'title' => $requirement->title], $request);
        });

        return back()->with('success', 'Requerimiento registrado y vinculado al ticket.');
    }

    public function update(Request $request, DevelopmentRequest $developmentRequest, DevelopmentRequirement $requirement): RedirectResponse
    {
        $this->ensureBelongs($developmentRequest, $requirement);
        $this->authorize('manageRequirements', $developmentRequest);
        $validated = $this->validatedRequirement($request, $developmentRequest, $requirement);

        DB::transaction(function () use ($request, $developmentRequest, $requirement, $validated): void {
            $before = $requirement->only(['type', 'title', 'priority', 'status']);
            $requirement->update($this->payload($validated) + [
                'code' => ($validated['code'] ?? null) ?: $requirement->code,
                'updated_by_id' => $request->user()->id,
            ]);
            $developmentRequest->update(['lifecycle_status' => $requirement->status === 'implemented' ? 'development' : $developmentRequest->lifecycle_status]);
            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'development_requirement_updated',
                'Requerimiento '.$requirement->code.' actualizado.', $before, $requirement->only(array_keys($before)), $request);
        });

        return back()->with('success', 'Requerimiento actualizado.');
    }

    private function validatedRequirement(Request $request, DevelopmentRequest $developmentRequest, ?DevelopmentRequirement $requirement = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('development_requirements', 'code')
                ->where('development_request_id', $developmentRequest->id)->ignore($requirement?->id)],
            'type' => ['required', Rule::in(array_keys(DevelopmentRequirement::TYPES))],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:5', 'max:20000'],
            'acceptance_criteria' => ['nullable', 'string', 'max:15000'],
            'technical_restrictions' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(array_keys(DevelopmentRequirement::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(DevelopmentRequirement::STATUSES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    private function payload(array $validated): array
    {
        return [
            'type' => $validated['type'],
            'title' => $this->development->clean($validated['title']),
            'description' => $this->development->clean($validated['description']),
            'acceptance_criteria' => $this->development->clean($validated['acceptance_criteria'] ?? null),
            'technical_restrictions' => $this->development->clean($validated['technical_restrictions'] ?? null),
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ];
    }

    private function nextCode(DevelopmentRequest $developmentRequest): string
    {
        $next = (int) $developmentRequest->requirements()->lockForUpdate()->count() + 1;

        do {
            $code = sprintf('REQ-%03d', $next++);
        } while ($developmentRequest->requirements()->where('code', $code)->exists());

        return $code;
    }

    private function ensureBelongs(DevelopmentRequest $developmentRequest, DevelopmentRequirement $requirement): void
    {
        if ($requirement->development_request_id !== $developmentRequest->id) {
            abort(404);
        }
    }
}
