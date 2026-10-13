<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanEquipment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', MaintenanceOrder::class);
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['month', 'quarter', 'year'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'group_by' => ['nullable', Rule::in(['month', 'department', 'location'])],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $scope = $filters['scope'] ?? 'month';
        $focus = isset($filters['date']) ? Carbon::createFromFormat('Y-m-d', $filters['date']) : today();
        [$start, $end] = match ($scope) {
            'quarter' => [$focus->copy()->startOfQuarter(), $focus->copy()->endOfQuarter()],
            'year' => [$focus->copy()->startOfYear(), $focus->copy()->endOfYear()],
            default => [$focus->copy()->startOfMonth(), $focus->copy()->endOfMonth()],
        };

        $equipmentFilter = function ($query) use ($filters): void {
            $query->when($filters['area_id'] ?? null, fn ($query, $id) => $query->where('area_id', $id))
                ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
                ->when($filters['location'] ?? null, fn ($query, $location) => $query->where('location', $location));
        };

        $orders = MaintenanceOrder::with(['plan', 'equipment.area', 'equipment.department', 'assignedTo', 'ticket'])
            ->whereBetween('scheduled_for', [$start->toDateString(), $end->toDateString()])
            ->whereHas('equipment', $equipmentFilter)
            ->orderBy('scheduled_for')->orderBy('number')->get();
        $orderKeys = $orders->mapWithKeys(fn (MaintenanceOrder $order) => [
            $order->maintenance_plan_equipment_id.'|'.$order->scheduled_for->toDateString() => true,
        ]);
        $planned = MaintenancePlanEquipment::with(['plan', 'equipment.area', 'equipment.department'])
            ->where('is_active', true)
            ->whereBetween('next_due_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->whereHas('equipment', $equipmentFilter)
            ->orderBy('next_due_date')->get()
            ->reject(fn ($assignment) => $orderKeys->has($assignment->id.'|'.$assignment->next_due_date->toDateString()));

        $entries = $orders->map(fn ($order) => (object) [
            'type' => 'order', 'date' => $order->scheduled_for, 'order' => $order,
            'plan' => $order->plan, 'equipment' => $order->equipment,
        ])->concat($planned->map(fn ($assignment) => (object) [
            'type' => 'planned', 'date' => $assignment->next_due_date, 'assignment' => $assignment,
            'plan' => $assignment->plan, 'equipment' => $assignment->equipment,
        ]))->sortBy('date')->values();

        $groupBy = $filters['group_by'] ?? 'month';
        $groupedEntries = $entries->groupBy(fn ($entry) => match ($groupBy) {
            'department' => $entry->equipment->department?->name ?? 'Sin departamento',
            'location' => $entry->equipment->location ?: 'Sin ubicación',
            default => $entry->date->locale('es')->translatedFormat('F Y'),
        });

        return view('maintenance.index', [
            'filters' => $filters + ['scope' => $scope, 'group_by' => $groupBy],
            'start' => $start,
            'end' => $end,
            'groupedEntries' => $groupedEntries,
            'plans' => MaintenancePlan::withCount([
                'assignments as active_equipment_count' => fn ($query) => $query->where('is_active', true),
                'orders as open_orders_count' => fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']),
            ])->orderByDesc('is_active')->orderBy('name')->get(),
            'metrics' => [
                'plans' => MaintenancePlan::where('is_active', true)->count(),
                'dueSoon' => MaintenancePlanEquipment::where('is_active', true)->whereBetween('next_due_date', [today(), today()->addDays(30)])->count(),
                'open' => MaintenanceOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'overdue' => MaintenanceOrder::whereNotIn('status', ['completed', 'cancelled'])->whereDate('scheduled_for', '<', today())->count(),
            ],
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::where('is_active', true)->orderBy('name')->get(),
            'locations' => Equipment::where('is_active', true)->distinct()->orderBy('location')->pluck('location')->filter(),
        ]);
    }
}
