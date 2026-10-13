<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Supply;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleSixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_inventory_catalogs_are_protected_and_render_for_authorized_it_users(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');

        $this->actingAs($admin)->get(route('inventory.index'))->assertOk()->assertSee('Inventario tecnológico');
        $this->actingAs($admin)->get(route('inventory.supplies.index'))->assertOk()->assertSee('Insumos técnicos');
        $this->actingAs($requester)->get(route('inventory.index'))->assertForbidden();
        $this->assertDatabaseCount('equipment', 5);
        $this->assertDatabaseCount('supplies', 5);
    }

    public function test_equipment_registration_validates_unique_identity_network_and_organization(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $area = Area::where('code', 'IT-INF')->firstOrFail();
        $department = Department::where('code', 'INF-RED')->firstOrFail();
        $responsible = $this->user('infraestructura@zolicguate.com');

        $payload = [
            'inventory_code' => 'zolic-ap-100',
            'asset_type' => 'network_device',
            'name' => 'Punto de acceso administrativo',
            'brand' => 'Cisco',
            'model' => 'Catalyst 9120',
            'serial_number' => 'AP-9120-100',
            'ip_address' => '10.10.8.100',
            'mac_address' => 'AA:BB:CC:DD:EE:10',
            'hostname' => 'AP-ADM-100',
            'network_segment' => '10.10.8.0/24',
            'area_id' => $area->id,
            'department_id' => $department->id,
            'responsible_user_id' => $responsible->id,
            'location' => 'Edificio administrativo · Nivel 2',
            'status' => 'assigned',
            'is_active' => 1,
            'additional_specifications_text' => "PoE: 802.3at\nAntenas: Integradas",
        ];
        $this->actingAs($admin)->post(route('inventory.equipment.store'), $payload)->assertRedirect();
        $equipment = Equipment::where('inventory_code', 'ZOLIC-AP-100')->firstOrFail();

        $this->assertSame('10.10.8.100', $equipment->ip_address);
        $this->assertSame('802.3at', $equipment->additional_specifications['PoE']);
        $this->assertDatabaseHas('equipment_events', ['equipment_id' => $equipment->id, 'title' => 'Alta en inventario']);

        $this->actingAs($admin)->post(route('inventory.equipment.store'), $payload)
            ->assertSessionHasErrors(['inventory_code', 'serial_number', 'mac_address']);

        $foreignDepartment = Department::where('area_id', '!=', $area->id)->firstOrFail();
        $this->actingAs($admin)->post(route('inventory.equipment.store'), array_replace($payload, [
            'inventory_code' => 'ZOLIC-AP-101', 'serial_number' => 'AP-9120-101',
            'mac_address' => 'AA:BB:CC:DD:EE:11', 'department_id' => $foreignDepartment->id,
        ]))->assertSessionHasErrors('department_id');
    }

    public function test_printable_label_contains_a_code39_barcode_and_inventory_identity(): void
    {
        $equipment = Equipment::where('inventory_code', 'ZOLIC-PC-001')->firstOrFail();

        $this->actingAs($this->user('admin@zolicguate.com'))->get(route('inventory.equipment.label', $equipment))
            ->assertOk()->assertSee('Código de barras')->assertSee('ZOLIC-PC-001')->assertSee('<rect', false);
    }

    public function test_assignment_transfer_and_retirement_require_an_authorized_user_and_update_custody(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $equipment = Equipment::where('inventory_code', 'ZOLIC-PRN-003')->firstOrFail();

        $payload = [
            'movement_type' => 'assignment',
            'to_area_id' => $requester->area_id,
            'to_department_id' => $requester->department_id,
            'to_responsible_id' => $requester->id,
            'to_location' => 'Operaciones Portuarias · Recepción',
            'to_status' => 'assigned',
            'effective_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'justification' => 'Asignación autorizada para impresión de documentos operativos.',
            'authorized_by_id' => $requester->id,
        ];
        $this->actingAs($admin)->post(route('inventory.equipment.movements.store', $equipment), $payload)
            ->assertSessionHasErrors('authorized_by_id');

        $this->actingAs($admin)->post(route('inventory.equipment.movements.store', $equipment), array_replace($payload, [
            'authorized_by_id' => $admin->id,
        ]))->assertRedirect();
        $equipment->refresh();
        $this->assertSame($requester->id, $equipment->responsible_user_id);
        $this->assertSame('assigned', $equipment->status);
        $this->assertDatabaseHas('equipment_movements', [
            'equipment_id' => $equipment->id, 'movement_type' => 'assignment', 'authorized_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('inventory.equipment.movements.store', $equipment), [
            'movement_type' => 'retirement', 'to_status' => 'retired',
            'to_location' => 'Bodega de bajas', 'effective_at' => now()->format('Y-m-d H:i:s'),
            'justification' => 'Baja definitiva autorizada por obsolescencia tecnológica.', 'authorized_by_id' => $admin->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'status' => 'retired', 'is_active' => false, 'responsible_user_id' => null]);
    }

    public function test_technical_history_can_link_only_tickets_visible_to_the_actor(): void
    {
        $technician = $this->user('infraestructura@zolicguate.com');
        $equipment = Equipment::where('inventory_code', 'ZOLIC-SRV-001')->firstOrFail();
        $hiddenTicket = Ticket::create([
            'number' => 'ZT-'.now()->year.'-999999',
            'requester_id' => $this->user('temporal@zolicguate.com')->id,
            'created_by_id' => $this->user('temporal@zolicguate.com')->id,
            'source' => 'requester', 'is_draft' => true,
            'ticket_category_id' => TicketCategory::firstOrFail()->id,
            'ticket_status_id' => TicketStatus::where('is_initial', true)->value('id'),
        ]);
        $payload = [
            'event_type' => 'maintenance', 'title' => 'Limpieza preventiva',
            'description' => 'Se realizó limpieza, revisión de ventiladores y validación de temperatura.',
            'performed_at' => now()->subMinute()->format('Y-m-d H:i:s'), 'ticket_id' => $hiddenTicket->id,
        ];

        $this->actingAs($technician)->post(route('inventory.equipment.events.store', $equipment), $payload)
            ->assertSessionHasErrors('ticket_id');
        $this->actingAs($technician)->post(route('inventory.equipment.events.store', $equipment), array_replace($payload, ['ticket_id' => null]))
            ->assertRedirect();
        $this->assertDatabaseHas('equipment_events', ['equipment_id' => $equipment->id, 'title' => 'Limpieza preventiva']);
    }

    public function test_stock_movements_never_allow_negative_or_below_reserved_inventory(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $supply = Supply::where('code', 'INS-SSD-1TB')->firstOrFail();
        $starting = (float) $supply->stock_quantity;

        $this->actingAs($admin)->post(route('inventory.supplies.movements.store', $supply), [
            'movement_type' => 'exit', 'quantity' => $starting + 1,
            'justification' => 'Salida de inventario solicitada para validación.', 'authorized_by_id' => $admin->id,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);

        $this->actingAs($admin)->post(route('inventory.supplies.movements.store', $supply), [
            'movement_type' => 'entry', 'quantity' => 5,
            'justification' => 'Ingreso de unidades recibido y validado contra orden de compra.', 'authorized_by_id' => $admin->id,
        ])->assertRedirect();
        $this->assertSame($starting + 5, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('supply_movements', ['supply_id' => $supply->id, 'movement_type' => 'entry', 'quantity_delta' => 5]);
    }

    public function test_reserved_supply_is_consumed_once_when_the_requester_accepts_the_solution(): void
    {
        $technician = $this->user('soporte@zolicguate.com');
        $ticket = $this->workableTicket($technician);
        $requester = $ticket->requester;
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        $starting = (float) $supply->stock_quantity;

        $this->actingAs($technician)->post(route('tickets.supplies.store', $ticket), [
            'supply_id' => $supply->id, 'quantity' => 3, 'notes' => 'Conectores utilizados en reparación de punto de red.',
        ])->assertRedirect();
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'supply_id' => $supply->id, 'status' => 'reserved']);

        $this->actingAs($technician)->post(route('tickets.solution.store', $ticket), [
            'solution' => 'Se reemplazaron conectores, se certificó el enlace y se validó la conectividad correctamente.',
        ])->assertRedirect();
        $this->actingAs($requester)->post(route('tickets.review.store', $ticket->fresh()), [
            'decision' => 'accepted', 'rating' => 5, 'confirmation' => 1,
        ])->assertRedirect();
        $this->assertSame($starting - 3, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'supply_id' => $supply->id, 'status' => 'consumed', 'quantity_consumed' => 3]);
        $this->assertDatabaseHas('supply_movements', ['ticket_id' => $ticket->id, 'supply_id' => $supply->id, 'movement_type' => 'consumption']);

        $this->actingAs($requester)->patch(route('tickets.reopen', $ticket->fresh()), [
            'reason' => 'Se requiere una verificación adicional del enlace reparado.',
        ])->assertRedirect();
        $this->actingAs($technician)->post(route('tickets.solution.store', $ticket->fresh()), [
            'solution' => 'Se verificó nuevamente el enlace y se confirmó que opera sin pérdida de paquetes.',
        ])->assertRedirect();
        $this->actingAs($requester)->post(route('tickets.review.store', $ticket->fresh()), [
            'decision' => 'accepted', 'rating' => 5, 'confirmation' => 1,
        ])->assertRedirect();
        $this->assertSame($starting - 3, (float) $supply->fresh()->stock_quantity);
        $this->assertSame(1, $supply->movements()->where('ticket_id', $ticket->id)->where('movement_type', 'consumption')->count());
    }

    public function test_cancelling_a_ticket_releases_reservations_without_changing_stock(): void
    {
        $technician = $this->user('soporte@zolicguate.com');
        $ticket = $this->workableTicket($technician);
        $supply = Supply::where('code', 'INS-CAB-C6')->firstOrFail();
        $starting = (float) $supply->stock_quantity;

        $this->actingAs($technician)->post(route('tickets.supplies.store', $ticket), [
            'supply_id' => $supply->id, 'quantity' => 10, 'notes' => 'Cable reservado para atención en sitio.',
        ])->assertRedirect();
        $this->actingAs($ticket->requester)->patch(route('tickets.cancel', $ticket), [
            'reason' => 'La solicitud ya no es necesaria debido a un cambio operativo.',
        ])->assertRedirect();

        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'supply_id' => $supply->id, 'status' => 'released']);
        $this->assertDatabaseMissing('supply_movements', ['ticket_id' => $ticket->id, 'movement_type' => 'consumption']);
    }

    private function workableTicket(User $technician): Ticket
    {
        return Ticket::with('requester')->where('current_assignee_id', $technician->id)
            ->where('is_draft', false)->whereNotIn('approval_status', ['pending', 'rejected', 'changes_requested'])
            ->whereHas('status', fn ($query) => $query->whereIn('slug', ['asignado', 'en-analisis', 'en-proceso']))
            ->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
