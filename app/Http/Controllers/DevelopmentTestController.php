<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentTestEvidence;
use App\Services\DevelopmentRequestService;
use App\Services\TicketAttachmentService;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class DevelopmentTestController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketAttachmentService $attachments,
        private TicketHistoryService $history,
    ) {}

    public function store(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('manageTests', $developmentRequest);
        $validated = $request->validate([
            'test_type' => ['required', Rule::in(array_keys(DevelopmentTestEvidence::TYPES))],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'environment' => ['required', 'string', 'min:2', 'max:80'],
            'result' => ['required', Rule::in(array_keys(DevelopmentTestEvidence::RESULTS))],
            'executed_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', File::types(TicketAttachmentService::ALLOWED_EXTENSIONS)->max(10 * 1024)],
        ], [
            'attachments.required' => 'Adjunta al menos una evidencia verificable de la prueba.',
            'attachments.*' => 'Una evidencia no es válida, supera 10 MB o tiene un tipo no permitido.',
        ]);

        DB::transaction(function () use ($request, $developmentRequest, $validated): void {
            $evidence = $developmentRequest->testEvidences()->create([
                'test_type' => $validated['test_type'],
                'title' => $this->development->clean($validated['title']),
                'environment' => $this->development->clean($validated['environment']),
                'result' => $validated['result'],
                'executed_at' => $validated['executed_at'],
                'executed_by_id' => $request->user()->id,
                'notes' => $this->development->clean($validated['notes'] ?? null),
            ]);
            $this->attachments->storeMany(
                $developmentRequest->ticket,
                $request->user(),
                $request->file('attachments'),
                'development_test',
                true,
                null,
                null,
                $evidence,
            );
            $developmentRequest->update(['lifecycle_status' => 'testing']);
            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'development_test_recorded',
                $evidence->type_label.' registrada con resultado «'.$evidence->result_label.'».', [], [
                    'environment' => $evidence->environment,
                    'result' => $evidence->result,
                ], $request);
        });

        return back()->with('success', 'Evidencia de prueba registrada en almacenamiento privado.');
    }
}
