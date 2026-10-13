<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalWorkflow;
use App\Models\Area;
use App\Models\FormField;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_every_module_three_view(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('configuration.dashboard'))->assertOk()->assertSee('El sistema se adapta');
        $this->actingAs($admin)->get(route('configuration.categories.index'))->assertOk()->assertSee('Categorías y subcategorías');
        $this->actingAs($admin)->get(route('configuration.forms.index'))->assertOk()->assertSee('Diseñador de formularios');
        $this->actingAs($admin)->get(route('configuration.forms.create'))->assertOk()->assertSee('Crear formulario');
        $this->actingAs($admin)->get(route('configuration.approvals.index'))->assertOk()->assertSee('Reglas de aprobación');
        $this->actingAs($admin)->get(route('configuration.approvals.create'))->assertOk()->assertSee('Nueva regla de aprobación');
        $this->actingAs($admin)->get(route('configuration.routing.index'))->assertOk()->assertSee('Reglas de direccionamiento');
        $this->actingAs($admin)->get(route('configuration.service-levels.index'))->assertOk()->assertSee('Estados, prioridades y SLA');
    }

    public function test_categories_and_subcategories_are_created_and_audited(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('configuration.categories.store'), [
            'code' => 'SEG', 'name' => 'Seguridad informática', 'type' => 'incident',
            'description' => 'Eventos de seguridad.', 'sort_order' => 10, 'is_active' => 1,
        ])->assertRedirect(route('configuration.categories.index'));

        $category = TicketCategory::where('code', 'SEG')->firstOrFail();
        $this->actingAs($admin)->post(route('configuration.subcategories.store'), [
            'ticket_category_id' => $category->id, 'code' => 'PHI', 'name' => 'Correo sospechoso',
            'description' => 'Posible phishing.', 'sort_order' => 10, 'is_active' => 1,
        ])->assertRedirect(route('configuration.categories.index'));

        $this->assertDatabaseHas('ticket_subcategories', ['ticket_category_id' => $category->id, 'code' => 'PHI']);
        $this->assertDatabaseHas('security_events', ['event' => 'category_created', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'subcategory_created', 'user_id' => $admin->id]);
    }

    public function test_form_designer_associates_classification_and_sanitizes_field_options(): void
    {
        $admin = $this->superAdmin();
        [$category, $subcategory] = $this->classification();

        $this->actingAs($admin)->post(route('configuration.forms.store'), [
            'ticket_category_id' => $category->id, 'ticket_subcategory_id' => $subcategory->id,
            'code' => 'FRM-SEC', 'name' => 'Reporte de seguridad', 'description' => 'Datos del evento.', 'is_active' => 1,
        ])->assertRedirect();

        $form = TicketForm::where('code', 'FRM-SEC')->firstOrFail();
        $this->actingAs($admin)->post(route('configuration.fields.store', $form), [
            'name' => 'clasificacion', 'label' => 'Clasificación', 'type' => 'select',
            'options_text' => "Interno\nConfidencial<script>alert(1)</script>", 'sort_order' => 10,
            'is_required' => 1, 'is_visible' => 1, 'is_readonly' => 0, 'is_active' => 1,
        ])->assertRedirect(route('configuration.forms.edit', $form));

        $field = FormField::where('ticket_form_id', $form->id)->firstOrFail();
        $this->assertTrue($field->is_required);
        $this->assertCount(2, $field->options);
        $this->assertStringNotContainsString('<script>', implode('', $field->options));
        $this->assertDatabaseHas('security_events', ['event' => 'form_field_created', 'user_id' => $admin->id]);
    }

    public function test_form_rejects_a_subcategory_from_another_category(): void
    {
        $admin = $this->superAdmin();
        [$category] = $this->classification();
        $other = TicketCategory::create(['code' => 'OTH', 'name' => 'Otra', 'type' => 'request', 'is_active' => true]);
        $foreign = TicketSubcategory::create(['ticket_category_id' => $other->id, 'code' => 'SUB', 'name' => 'Ajena']);

        $this->actingAs($admin)->post(route('configuration.forms.store'), [
            'ticket_category_id' => $category->id, 'ticket_subcategory_id' => $foreign->id,
            'code' => 'FRM-BAD', 'name' => 'Formulario inválido', 'is_active' => 1,
        ])->assertSessionHasErrors('ticket_subcategory_id');

        $this->assertDatabaseMissing('ticket_forms', ['code' => 'FRM-BAD']);
    }

    public function test_multilevel_approval_and_temporary_delegation_are_configurable(): void
    {
        $admin = $this->superAdmin();
        [$category] = $this->classification();
        $role = Role::create(['name' => 'Jefatura de IT', 'slug' => 'jefatura-it', 'is_active' => true]);

        $this->actingAs($admin)->post(route('configuration.approvals.store'), [
            'ticket_category_id' => $category->id, 'code' => 'APR-SEC', 'name' => 'Aprobación de seguridad',
            'description' => 'Autorización previa.', 'is_active' => 1,
        ])->assertRedirect();
        $workflow = ApprovalWorkflow::where('code', 'APR-SEC')->firstOrFail();

        $this->actingAs($admin)->post(route('configuration.approval-levels.store', $workflow), [
            'level_number' => 1, 'name' => 'Validación de IT', 'approver_type' => 'role',
            'approver_role_id' => $role->id, 'is_required' => 1, 'allow_delegation' => 1, 'is_active' => 1,
        ])->assertRedirect(route('configuration.approvals.edit', $workflow));

        $holder = User::factory()->create(['email' => 'titular@zolicguate.com']);
        $delegate = User::factory()->create(['email' => 'suplente@zolicguate.com']);
        $this->actingAs($admin)->post(route('configuration.delegations.store'), [
            'delegator_user_id' => $holder->id, 'delegate_user_id' => $delegate->id,
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'reason' => 'Vacaciones programadas', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('approval_levels', ['approval_workflow_id' => $workflow->id, 'level_number' => 1, 'approver_role_id' => $role->id]);
        $this->assertDatabaseHas('approval_delegations', ['delegator_user_id' => $holder->id, 'delegate_user_id' => $delegate->id]);
    }

    public function test_overlapping_delegations_for_same_holder_are_rejected(): void
    {
        $admin = $this->superAdmin();
        $holder = User::factory()->create();
        $delegate = User::factory()->create();
        $otherDelegate = User::factory()->create();
        ApprovalDelegation::create([
            'delegator_user_id' => $holder->id, 'delegate_user_id' => $delegate->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(5), 'reason' => 'Ausencia',
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('configuration.delegations.store'), [
            'delegator_user_id' => $holder->id, 'delegate_user_id' => $otherDelegate->id,
            'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'), 'ends_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'reason' => 'Otra ausencia', 'is_active' => 1,
        ])->assertSessionHasErrors('starts_at');

        $this->assertDatabaseCount('approval_delegations', 1);
    }

    public function test_routing_rule_requires_a_valid_technical_destination(): void
    {
        $admin = $this->superAdmin();
        [$category, $subcategory] = $this->classification();
        $parent = Area::create(['code' => 'IT', 'name' => 'Tecnología', 'type' => 'it', 'is_active' => true]);
        $area = Area::create(['code' => 'IT-SEG', 'name' => 'Seguridad', 'type' => 'it', 'parent_id' => $parent->id, 'is_active' => true]);
        $technician = User::factory()->create();
        $role = Role::create(['name' => 'Técnico IT', 'slug' => 'tecnico-it', 'is_active' => true]);
        $technician->roles()->attach($role);
        $group = TechnicalGroup::create(['area_id' => $area->id, 'code' => 'GRP-SEG', 'name' => 'Seguridad', 'is_active' => true]);
        $group->members()->attach($technician, ['membership_role' => 'member']);

        $this->actingAs($admin)->post(route('configuration.routing.store'), [
            'name' => 'Eventos de seguridad', 'ticket_category_id' => $category->id,
            'ticket_subcategory_id' => $subcategory->id, 'it_area_id' => $parent->id,
            'it_group_id' => $group->id, 'assignee_user_id' => $technician->id, 'precedence' => 10, 'is_active' => 1,
        ])->assertRedirect(route('configuration.routing.index'));

        $this->assertDatabaseHas('routing_rules', ['name' => 'Eventos de seguridad', 'it_group_id' => $group->id]);

        $this->actingAs($admin)->post(route('configuration.routing.store'), [
            'name' => 'Sin destino', 'ticket_category_id' => $category->id, 'precedence' => 20, 'is_active' => 1,
        ])->assertSessionHasErrors('it_area_id');
    }

    public function test_status_priority_matrix_and_sla_are_managed_consistently(): void
    {
        $admin = $this->superAdmin();
        [$category, $subcategory] = $this->classification();

        $this->actingAs($admin)->post(route('configuration.statuses.store'), [
            'name' => 'Registrado', 'color' => '#225C9A', 'sort_order' => 10, 'is_initial' => 1, 'is_active' => 1,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('configuration.priorities.store'), [
            'code' => 'P1', 'name' => 'Crítica', 'color' => '#C62828', 'weight' => 4, 'description' => 'Atención inmediata.', 'is_active' => 1,
        ])->assertRedirect();
        $impact = ImpactLevel::create(['name' => 'Alto', 'value' => 3, 'is_active' => true]);
        $urgency = UrgencyLevel::create(['name' => 'Alta', 'value' => 3, 'is_active' => true]);
        $priority = TicketPriority::where('code', 'P1')->firstOrFail();

        $this->actingAs($admin)->put(route('configuration.priority-matrix.update'), [
            'matrix' => [$impact->id => [$urgency->id => $priority->id]],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('configuration.sla-targets.store'), [
            'ticket_priority_id' => $priority->id, 'ticket_category_id' => $category->id,
            'ticket_subcategory_id' => $subcategory->id, 'response_minutes' => 15,
            'escalation_minutes' => 60, 'resolution_minutes' => 240,
            'business_hours_only' => 1, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertTrue(TicketStatus::where('slug', 'registrado')->firstOrFail()->is_initial);
        $this->assertDatabaseHas('priority_matrix_rules', ['impact_level_id' => $impact->id, 'urgency_level_id' => $urgency->id, 'ticket_priority_id' => $priority->id]);
        $this->assertDatabaseHas('service_level_targets', ['ticket_priority_id' => $priority->id, 'resolution_minutes' => 240]);
    }

    public function test_server_side_permissions_prevent_unauthorized_configuration_changes(): void
    {
        $view = Permission::create(['module' => 'Parametrización', 'action' => 'Consultar', 'name' => 'Consultar', 'slug' => 'configuration.view']);
        $role = Role::create(['name' => 'Consulta de configuración', 'slug' => 'consulta-configuracion', 'is_active' => true]);
        $role->permissions()->attach($view);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('configuration.categories.index'))->assertOk();
        $this->actingAs($user)->post(route('configuration.categories.store'), [
            'code' => 'BAD', 'name' => 'No autorizada', 'type' => 'request', 'sort_order' => 1, 'is_active' => 1,
        ])->assertForbidden();
        $this->assertDatabaseMissing('ticket_categories', ['code' => 'BAD']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['email' => fake()->unique()->userName().'@zolicguate.com', 'is_super_admin' => true]);
    }

    private function classification(): array
    {
        $category = TicketCategory::create(['code' => 'SEC', 'name' => 'Seguridad', 'type' => 'incident', 'is_active' => true]);
        $subcategory = TicketSubcategory::create([
            'ticket_category_id' => $category->id, 'code' => 'EVT', 'name' => 'Evento de seguridad', 'is_active' => true,
        ]);

        return [$category, $subcategory];
    }
}
