<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceEvidence;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderItem;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanEquipment;
use App\Services\MaintenanceService;
use App\Support\UploadSecurityPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceOrderController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    public function show(MaintenanceOrder $order): View
    {
        $this->authorize('view', $order);
        $order->load([
            'plan.area', 'plan.group', 'equipment.area', 'equipment.department', 'equipment.responsible',
            'ticket.status', 'assignedTo', 'createdBy', 'completedBy', 'items.completedBy', 'evidences.uploadedBy',
        ]);

        return view('maintenance.orders.show', ['order' => $order]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('generate', MaintenanceOrder::class);
        $orders = $this->maintenance->generateDueOrders($request->user());

        return back()->with('success', $orders->count().' orden(es) preventiva(s) generada(s).');
    }

    public function generateAssignment(
        Request $request,
        MaintenancePlan $plan,
        MaintenancePlanEquipment $assignment,
    ): RedirectResponse {
        $this->authorize('generate', MaintenanceOrder::class);
        $this->authorize('schedule', $plan);
        abort_unless($assignment->maintenance_plan_id === $plan->id, 404);
        $order = $this->maintenance->generateForAssignment($assignment, $request->user());

        return redirect()->route('maintenance.orders.show', $order)->with('success', 'Orden preventiva generada.');
    }

    public function start(Request $request, MaintenanceOrder $order): RedirectResponse
    {
        $this->authorize('execute', $order);
        $this->maintenance->start($order, $request->user());

        return back()->with('success', 'Ejecución del mantenimiento iniciada.');
    }

    public function complete(Request $request, MaintenanceOrder $order): RedirectResponse
    {
        $this->authorize('execute', $order);
        $validated = $request->validate([
            'result' => ['required', Rule::in(array_keys(MaintenanceOrder::RESULTS))],
            'observations' => ['required', 'string', 'min:10', 'max:10000'],
            'items' => ['required', 'array'],
            'items.*.status' => ['required', Rule::in(array_keys(MaintenanceOrderItem::STATUSES))],
            'items.*.observation' => ['nullable', 'string', 'max:2000'],
            'evidences' => ['nullable', 'array', 'max:'.UploadSecurityPolicy::MAX_FILES_PER_REQUEST],
            'evidences.*' => UploadSecurityPolicy::fileRules(MaintenanceService::ALLOWED_EXTENSIONS),
            'evidence_description' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->maintenance->complete(
            $order,
            $validated['items'],
            $validated['result'],
            $validated['observations'],
            $request->file('evidences', []),
            $validated['evidence_description'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Mantenimiento documentado y orden completada.');
    }

    public function download(MaintenanceOrder $order, MaintenanceEvidence $evidence): BinaryFileResponse
    {
        $this->authorize('view', $order);
        abort_unless($evidence->maintenance_order_id === $order->id, 404);
        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return response()->download(
            Storage::disk($evidence->disk)->path($evidence->path),
            $evidence->original_name,
            ['Content-Type' => $evidence->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
