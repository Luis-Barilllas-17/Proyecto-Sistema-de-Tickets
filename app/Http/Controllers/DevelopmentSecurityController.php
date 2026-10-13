<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentSecurityControl;
use App\Services\DevelopmentRequestService;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DevelopmentSecurityController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketHistoryService $history,
    ) {}

    public function update(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('manageSecurity', $developmentRequest);
        $validated = $request->validate([
            'controls' => ['required', 'array', 'min:1', 'max:6'],
            'controls.*.is_required' => ['nullable', 'boolean'],
            'controls.*.requirement_details' => ['nullable', 'string', 'max:5000'],
            'controls.*.implementation_notes' => ['nullable', 'string', 'max:5000'],
            'controls.*.status' => ['required', Rule::in(array_keys(DevelopmentSecurityControl::STATUSES))],
        ]);

        $controls = $developmentRequest->securityControls()->whereIn('id', array_keys($validated['controls']))->get()->keyBy('id');
        if ($controls->count() !== count($validated['controls'])) {
            abort(404);
        }

        DB::transaction(function () use ($request, $developmentRequest, $validated, $controls): void {
            foreach ($validated['controls'] as $id => $input) {
                $control = $controls->get((int) $id);
                $required = filter_var($input['is_required'] ?? false, FILTER_VALIDATE_BOOL);
                if ($required && $input['status'] === 'not_applicable') {
                    throw ValidationException::withMessages(["controls.$id.status" => 'Un control requerido no puede marcarse como no aplicable.']);
                }
                if ($input['status'] === 'verified') {
                    $this->authorize('verifySecurity', $developmentRequest);
                }

                $control->update([
                    'is_required' => $required,
                    'requirement_details' => $this->development->clean($input['requirement_details'] ?? null),
                    'implementation_notes' => $this->development->clean($input['implementation_notes'] ?? null),
                    'status' => $input['status'],
                    'verified_by_id' => $input['status'] === 'verified' ? $request->user()->id : null,
                    'verified_at' => $input['status'] === 'verified' ? now() : null,
                ]);
            }

            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'development_security_updated',
                'Matriz de controles de seguridad actualizada.', [], [
                    'required' => $developmentRequest->securityControls()->where('is_required', true)->count(),
                    'verified' => $developmentRequest->securityControls()->where('status', 'verified')->count(),
                ], $request);
        });

        return back()->with('success', 'Controles de seguridad actualizados.');
    }
}
