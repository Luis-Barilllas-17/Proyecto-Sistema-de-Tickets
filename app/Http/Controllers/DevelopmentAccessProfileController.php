<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentRequest;
use App\Services\DevelopmentRequestService;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DevelopmentAccessProfileController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketHistoryService $history,
    ) {}

    public function store(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('manageSecurity', $developmentRequest);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:140', Rule::unique('development_access_profiles', 'name')->where('development_request_id', $developmentRequest->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'permissions' => ['required', 'string', 'min:3', 'max:5000'],
            'data_scope' => ['nullable', 'string', 'max:3000'],
            'includes_sensitive_data' => ['nullable', 'boolean'],
            'third_party_access' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $developmentRequest, $validated): void {
            $profile = $developmentRequest->accessProfiles()->create([
                'name' => $this->development->clean($validated['name']),
                'description' => $this->development->clean($validated['description'] ?? null),
                'permissions' => $this->development->clean($validated['permissions']),
                'data_scope' => $this->development->clean($validated['data_scope'] ?? null),
                'includes_sensitive_data' => $request->boolean('includes_sensitive_data'),
                'third_party_access' => $request->boolean('third_party_access'),
                'created_by_id' => $request->user()->id,
            ]);
            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'development_access_profile_created',
                'Perfil de acceso «'.$profile->name.'» registrado.', [], [
                    'sensitive_data' => $profile->includes_sensitive_data,
                    'third_party' => $profile->third_party_access,
                ], $request);
        });

        return back()->with('success', 'Perfil de acceso registrado.');
    }
}
