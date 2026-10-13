<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Equipment;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleSevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_calendar_plans_and_orders_are_protected_and_render_for_authorized_users(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $technician = $this->user('soporte@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');

        $this->actingAs($admin)->get(route('maintenance.index', ['scope' => 'year', 'group_by' => 'department']))
            ->assertOk()->assertSee('Programa preventivo')->assertSee('Calendario de mantenimientos');
        $this->actingAs($technician)->get(route('maintenance.index'))->assertOk();
        $this->actingAs($requester)->get(route('maintenance.index'))->assertForbidden();
        $this->assertDatabaseCount('maintenance_plans', 2);
        $this->assertDatabaseCount('maintenance_orders', 2);
    }

    public function test_admin_can_create_a_formal_plan_with_equipment_and_checklist(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $equipment = Equipment::where('inventory_code', 'ZOLIC-PRN-003')->firstOrFail();
        $area = Area::where('code', 'IT-SOP')->firstOrFail();
        $group = TechnicalGroup::where('code', 'GRP-SOP')->firstOrFail();
        $technician = $this->user('soporte@zolicguate.com');

        $this->actingAs($admin)->post(route('maintenance.plans.store'), [
            'code' => 'pm-prn-sem',
            'name' => 'Mantenimiento semestral de impresoras',
            'description' => 'Programa institucional para limpieza y pruebas de impresión preventiva.',
            'frequency_months' => 6,
            'lead_days' => 10,
            'duration_minutes' => 75,
            'default_start_time' => '09:30',
            'assigned_it_area_id' => $area->id,
            'assigned_it_group_id' => $group->id,
            'assigned_user_id' => $technician->id,
            'first_due_date' => today()->addMonth()->toDateString(),
            'equipment_ids' => [$equipment->id],
            'checklist_text' => "Limpieza interna y externa\nPrueba de consumibles\nPágina de diagnóstico",
            'is_active' => 1,
        ])->assertRedirect();

        $plan = MaintenancePlan::where('code', 'PM-PRN-SEM')->firstOrFail();
        $this->assertSame(3, $plan->checklistItems()->count());
        $this->assertDatabaseHas('maintenance_plan_equipment', [
            'maintenance_plan_id' => $plan->id,
            'equipment_id' => $equipment->id,
            'next_due_date' => today()->addMonth()->startOfDay(),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('security_events', ['event' => 'maintenance_plan_created']);
    }

    public function test_plan_rejects_a_technician_outside_the_selected_group(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $equipment = Equipment::where('inventory_code', 'ZOLIC-PRN-003')->firstOrFail();
        $area = Area::where('code', 'IT-SOP')->firstOrFail();
        $group = TechnicalGroup::where('code', 'GRP-SOP')->firstOrFail();

        $this->actingAs($admin)->post(route('maintenance.plans.store'), [
            'code' => 'PM-INVALID',
            'name' => 'Plan con asignación inválida',
            'description' => 'Este registro valida la coherencia del grupo y el técnico seleccionado.',
            'frequency_months' => 3,
            'lead_days' => 7,
            'duration_minutes' => 60,
            'assigned_it_area_id' => $area->id,
            'assigned_it_group_id' => $group->id,
            'assigned_user_id' => $this->user('aplicaciones@zolicguate.com')->id,
            'first_due_date' => today()->addMonth()->toDateString(),
            'equipment_ids' => [$equipment->id],
            'checklist_text' => 'Validar funcionamiento general',
            'is_active' => 1,
        ])->assertSessionHasErrors('assigned_user_id');
        $this->assertDatabaseMissing('maintenance_plans', ['code' => 'PM-INVALID']);
    }

    public function test_due_schedule_generates_only_one_order_and_linked_internal_ticket(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $plan = MaintenancePlan::where('code', 'PM-SRV-MEN')->firstOrFail();
        $assignment = $plan->assignments()->firstOrFail();
        $dueDate = today()->addDays(2);
        $assignment->update(['next_due_date' => $dueDate]);

        $this->actingAs($admin)->post(route('maintenance.orders.generate'))->assertRedirect();
        $order = MaintenanceOrder::where('maintenance_plan_id', $plan->id)
            ->where('equipment_id', $assignment->equipment_id)->firstOrFail();
        $this->assertSame($dueDate->toDateString(), $order->scheduled_for->toDateString());
        $this->assertSame('it_internal', $order->ticket->source);
        $this->assertFalse($order->ticket->is_draft);
        $this->assertSame($order->equipment_id, $order->ticket->equipmentLinks()->value('equipment_id'));
        $this->assertSame($dueDate->copy()->addMonthNoOverflow()->toDateString(), $assignment->fresh()->next_due_date->toDateString());

        $this->actingAs($admin)->post(route('maintenance.orders.generate'))->assertRedirect();
        $this->assertSame(1, MaintenanceOrder::where('maintenance_plan_id', $plan->id)->where('equipment_id', $assignment->equipment_id)->count());
    }

    public function test_technician_completes_checklist_with_private_evidence_and_updates_history(): void
    {
        Storage::fake('local');
        $technician = $this->user('soporte@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $order = MaintenanceOrder::whereHas('plan', fn ($query) => $query->where('code', 'PM-PC-TRI'))->firstOrFail();
        $previousStatus = $order->equipment->status;

        $this->actingAs($technician)->post(route('maintenance.orders.start', $order))->assertRedirect();
        $this->assertDatabaseHas('maintenance_orders', ['id' => $order->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('equipment', ['id' => $order->equipment_id, 'status' => 'maintenance']);

        $items = $order->fresh()->items->mapWithKeys(fn ($item) => [
            $item->id => ['status' => 'completed', 'observation' => 'Actividad realizada y validada.'],
        ])->all();
        $this->actingAs($technician)->post(route('maintenance.orders.complete', $order), [
            'result' => 'operational',
            'observations' => 'Equipo limpio, actualizado y validado sin hallazgos pendientes.',
            'items' => $items,
        ])->assertSessionHasErrors('evidences');

        $this->actingAs($technician)->post(route('maintenance.orders.complete', $order), [
            'result' => 'operational',
            'observations' => 'Equipo limpio, actualizado y validado sin hallazgos pendientes.',
            'items' => $items,
            'evidences' => [UploadedFile::fake()->create('resultado.pdf', 80, 'application/pdf')],
            'evidence_description' => 'Reporte técnico posterior al mantenimiento.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame($previousStatus, $order->equipment->fresh()->status);
        $this->assertDatabaseHas('maintenance_evidences', ['maintenance_order_id' => $order->id, 'original_name' => 'resultado.pdf']);
        $this->assertDatabaseHas('equipment_events', ['equipment_id' => $order->equipment_id, 'title' => 'Mantenimiento preventivo completado']);
        $this->assertSame('solucionado', TicketStatus::find($order->ticket->fresh()->ticket_status_id)->slug);

        $evidence = $order->evidences()->firstOrFail();
        $this->actingAs($requester)->get(route('maintenance.orders.evidences.download', [$order, $evidence]))->assertForbidden();
        $this->actingAs($technician)->get(route('maintenance.orders.evidences.download', [$order, $evidence]))->assertDownload('resultado.pdf');
    }

    public function test_required_checklist_item_cannot_be_left_pending_or_skipped_without_reason(): void
    {
        Storage::fake('local');
        $technician = $this->user('soporte@zolicguate.com');
        $order = MaintenanceOrder::whereHas('plan', fn ($query) => $query->where('code', 'PM-PC-TRI'))->firstOrFail();
        $this->actingAs($technician)->post(route('maintenance.orders.start', $order))->assertRedirect();
        $items = $order->fresh()->items;
        $payload = $items->mapWithKeys(fn ($item) => [$item->id => ['status' => 'completed', 'observation' => null]])->all();
        $payload[$items->first()->id] = ['status' => 'not_applicable', 'observation' => 'No'];

        $this->actingAs($technician)->post(route('maintenance.orders.complete', $order), [
            'result' => 'follow_up',
            'observations' => 'Se requiere seguimiento de una actividad que no pudo ejecutarse.',
            'items' => $payload,
            'evidences' => [UploadedFile::fake()->create('evidencia.pdf', 40, 'application/pdf')],
        ])->assertSessionHasErrors('items.'.$items->first()->id.'.observation');
        $this->assertDatabaseHas('maintenance_orders', ['id' => $order->id, 'status' => 'in_progress']);
        $this->assertDatabaseCount('maintenance_evidences', 0);
    }

    public function test_technician_cannot_execute_an_order_assigned_outside_his_scope(): void
    {
        $assignedTechnician = $this->user('soporte@zolicguate.com');
        $otherTechnician = $this->user('aplicaciones@zolicguate.com');
        $otherTechnician->roles()->syncWithoutDetaching(Role::where('slug', 'tecnico-it')->firstOrFail());
        $order = MaintenanceOrder::whereHas('plan', fn ($query) => $query->where('code', 'PM-PC-TRI'))->firstOrFail();

        $this->assertSame($assignedTechnician->id, $order->assigned_to_id);
        $this->assertTrue($otherTechnician->hasPermission('maintenance.execute'));
        $this->actingAs($otherTechnician)->post(route('maintenance.orders.start', $order))->assertForbidden();
        $this->assertDatabaseHas('maintenance_orders', ['id' => $order->id, 'status' => 'work_order_generated']);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
