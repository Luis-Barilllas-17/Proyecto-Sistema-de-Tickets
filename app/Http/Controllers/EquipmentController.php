<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    public function __construct(private InventoryService $inventory, private BarcodeService $barcode) {}

    public function create(): View
    {
        $this->authorize('create', Equipment::class);

        return view('inventory.equipment.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Equipment::class);
        $validated = $this->validatedEquipment($request);

        $equipment = DB::transaction(function () use ($request, $validated): Equipment {
            $equipment = Equipment::create($this->payload($validated) + [
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);
            $equipment->events()->create([
                'event_type' => 'note',
                'title' => 'Alta en inventario',
                'description' => 'Equipo registrado en el inventario institucional.',
                'performed_at' => now(),
                'metadata' => ['inventory_code' => $equipment->inventory_code, 'status' => $equipment->status],
                'created_by_id' => $request->user()->id,
            ]);

            return $equipment;
        });

        return redirect()->route('inventory.equipment.show', $equipment)->with('success', 'Equipo registrado en el inventario.');
    }

    public function show(Equipment $equipment): View
    {
        $this->authorize('view', $equipment);
        $equipment->load([
            'area', 'department', 'responsible', 'licenses.createdBy', 'events.ticket', 'events.createdBy',
            'movements.fromArea', 'movements.toArea', 'movements.fromResponsible', 'movements.toResponsible',
            'movements.authorizedBy', 'movements.createdBy', 'ticketLinks.ticket.status',
            'maintenanceAssignments.plan', 'maintenanceOrders.plan', 'maintenanceOrders.ticket',
        ]);
        $equipment->setRelation('ticketLinks', $equipment->ticketLinks
            ->filter(fn ($link) => $link->ticket && Gate::forUser(auth()->user())->allows('view', $link->ticket))->values());
        $tickets = Ticket::whereNotNull('submitted_at')->latest()->limit(100)->get(['id', 'number', 'title'])
            ->filter(fn (Ticket $ticket) => Gate::forUser(auth()->user())->allows('view', $ticket));

        return view('inventory.equipment.show', [
            'equipment' => $equipment,
            'areas' => Area::with(['departments' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
            'tickets' => $tickets,
        ]);
    }

    public function edit(Equipment $equipment): View
    {
        $this->authorize('update', $equipment);

        return view('inventory.equipment.form', $this->formData($equipment));
    }

    public function update(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('update', $equipment);
        $validated = $this->validatedEquipment($request, $equipment);

        DB::transaction(function () use ($request, $equipment, $validated): void {
            $before = $equipment->only(['inventory_code', 'asset_type', 'name', 'brand', 'model', 'serial_number', 'ip_address', 'mac_address', 'hostname', 'processor', 'memory_mb', 'storage_gb', 'operating_system', 'warranty_expires_at', 'condition_notes']);
            $equipment->update($this->payload($validated) + ['updated_by_id' => $request->user()->id]);
            $after = $equipment->fresh()->only(array_keys($before));
            $changes = collect($after)->filter(fn ($value, $key) => (string) $value !== (string) ($before[$key] ?? null));
            if ($changes->isNotEmpty()) {
                $equipment->events()->create([
                    'event_type' => 'change',
                    'title' => 'Ficha técnica actualizada',
                    'description' => 'Se actualizaron datos de identificación, red o especificaciones del activo.',
                    'performed_at' => now(),
                    'metadata' => ['before' => array_intersect_key($before, $changes->all()), 'after' => $changes->all()],
                    'created_by_id' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('inventory.equipment.show', $equipment)->with('success', 'Ficha del equipo actualizada.');
    }

    public function label(Equipment $equipment): View
    {
        $this->authorize('view', $equipment);

        return view('inventory.equipment.label', ['equipment' => $equipment, 'barcode' => $this->barcode->code39($equipment->inventory_code)]);
    }

    private function validatedEquipment(Request $request, ?Equipment $equipment = null): array
    {
        $request->merge([
            'inventory_code' => strtoupper(trim((string) $request->input('inventory_code'))),
            'mac_address' => $request->filled('mac_address')
                ? strtoupper(trim((string) $request->input('mac_address')))
                : null,
        ]);

        $validated = $request->validate([
            'inventory_code' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/-]*$/', Rule::unique('equipment', 'inventory_code')->ignore($equipment?->id)],
            'asset_type' => ['required', Rule::in(array_keys(Equipment::TYPES))],
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:150', Rule::unique('equipment', 'serial_number')->ignore($equipment?->id)],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', Rule::unique('equipment', 'mac_address')->ignore($equipment?->id)],
            'hostname' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9.-]*$/'],
            'network_segment' => ['nullable', 'string', 'max:180'],
            'connectivity_notes' => ['nullable', 'string', 'max:5000'],
            'processor' => ['nullable', 'string', 'max:180'],
            'memory_mb' => ['nullable', 'integer', 'between:1,4194304'],
            'storage_gb' => ['nullable', 'numeric', 'between:0.01,99999999.99'],
            'operating_system' => ['nullable', 'string', 'max:180'],
            'purchase_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'warranty_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'acquisition_cost' => ['nullable', 'numeric', 'between:0,999999999999.99'],
            'additional_specifications_text' => ['nullable', 'string', 'max:5000'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'location' => ['required', 'string', 'min:3', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Equipment::STATUSES))],
            'condition_notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $this->validateOrganization($validated);

        return $validated;
    }

    private function payload(array $validated): array
    {
        $text = $validated['additional_specifications_text'] ?? null;
        $additional = collect(preg_split('/\r\n|\r|\n/', (string) $text))->filter()->mapWithKeys(function ($line): array {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, null);

            return trim($key) !== '' && trim((string) $value) !== '' ? [trim($key) => trim($value)] : [];
        })->all();

        return [
            'inventory_code' => strtoupper($validated['inventory_code']),
            'asset_type' => $validated['asset_type'],
            'name' => $this->inventory->clean($validated['name']),
            'brand' => $this->inventory->clean($validated['brand'] ?? null),
            'model' => $this->inventory->clean($validated['model'] ?? null),
            'serial_number' => $this->inventory->clean($validated['serial_number'] ?? null),
            'ip_address' => $validated['ip_address'] ?? null,
            'mac_address' => isset($validated['mac_address']) ? strtoupper($validated['mac_address']) : null,
            'hostname' => $this->inventory->clean($validated['hostname'] ?? null),
            'network_segment' => $this->inventory->clean($validated['network_segment'] ?? null),
            'connectivity_notes' => $this->inventory->clean($validated['connectivity_notes'] ?? null),
            'processor' => $this->inventory->clean($validated['processor'] ?? null),
            'memory_mb' => $validated['memory_mb'] ?? null,
            'storage_gb' => $validated['storage_gb'] ?? null,
            'operating_system' => $this->inventory->clean($validated['operating_system'] ?? null),
            'purchase_date' => $validated['purchase_date'] ?? null,
            'warranty_expires_at' => $validated['warranty_expires_at'] ?? null,
            'acquisition_cost' => $validated['acquisition_cost'] ?? null,
            'additional_specifications' => $additional ?: null,
            'area_id' => $validated['area_id'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'] ?? null,
            'location' => $this->inventory->clean($validated['location']),
            'status' => $validated['status'],
            'condition_notes' => $this->inventory->clean($validated['condition_notes'] ?? null),
            'is_active' => ($validated['status'] ?? null) !== 'retired' && (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function validateOrganization(array $validated): void
    {
        if (! empty($validated['department_id']) && (empty($validated['area_id']) || ! Department::whereKey($validated['department_id'])->where('area_id', $validated['area_id'])->exists())) {
            throw ValidationException::withMessages(['department_id' => 'El departamento no pertenece al área seleccionada.']);
        }
        if (! empty($validated['responsible_user_id'])) {
            $user = User::findOrFail($validated['responsible_user_id']);
            if (! empty($validated['area_id']) && $user->area_id && $user->area_id !== (int) $validated['area_id']) {
                throw ValidationException::withMessages(['responsible_user_id' => 'El responsable no pertenece al área seleccionada.']);
            }
            if (! empty($validated['department_id']) && $user->department_id && $user->department_id !== (int) $validated['department_id']) {
                throw ValidationException::withMessages(['responsible_user_id' => 'El responsable no pertenece al departamento seleccionado.']);
            }
        }
    }

    private function formData(?Equipment $equipment = null): array
    {
        return [
            'equipment' => $equipment,
            'areas' => Area::with(['departments' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->where('is_active', true)->orderBy('name')->get(),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ];
    }
}
