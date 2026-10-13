<?php

namespace Tests\Feature;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Area;
use App\Models\FormField;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\PriorityMatrixRule;
use App\Models\Role;
use App\Models\RoutingRule;
use App\Models\ServiceLevelTarget;
use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleFourTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_can_be_saved_as_draft_with_a_unique_correlative_number(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'action' => 'draft', 'title' => null, 'description' => null,
        ]))->assertRedirect();
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'action' => 'draft', 'title' => 'Segundo borrador',
        ]))->assertRedirect();

        $this->assertDatabaseHas('tickets', ['number' => now()->format('\\Z\\T-Y-').'000001', 'is_draft' => true]);
        $this->assertDatabaseHas('tickets', ['number' => now()->format('\\Z\\T-Y-').'000002', 'is_draft' => true]);
        $this->assertDatabaseCount('ticket_histories', 2);
    }

    public function test_submitted_ticket_calculates_priority_sla_and_automatic_assignment(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data))->assertRedirect();
        $ticket = Ticket::firstOrFail();

        $this->assertFalse($ticket->is_draft);
        $this->assertSame($data['priority']->id, $ticket->ticket_priority_id);
        $this->assertSame($data['group']->id, $ticket->current_it_group_id);
        $this->assertSame($data['technician']->id, $ticket->current_assignee_id);
        $this->assertNotNull($ticket->response_due_at);
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->id, 'action' => 'automatic', 'status' => 'pending']);
        $this->actingAs($requester)->get(route('tickets.index'))->assertOk()->assertSee('Atención trazable')->assertSee($ticket->number);
        $this->actingAs($requester)->get(route('tickets.create'))->assertOk()->assertSee('¿Qué necesitas reportar?');
        $this->actingAs($requester)->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->number)->assertSee('Información del caso');
    }

    public function test_dynamic_form_rejects_tampered_options(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $form = TicketForm::create([
            'ticket_category_id' => $data['category']->id,
            'ticket_subcategory_id' => $data['subcategory']->id,
            'code' => 'FORM-SEC', 'name' => 'Formulario seguro', 'is_active' => true,
        ]);
        $field = FormField::create([
            'ticket_form_id' => $form->id, 'name' => 'classification', 'label' => 'Clasificación',
            'type' => 'select', 'options' => ['Interna', 'Pública'], 'sort_order' => 1,
            'is_required' => true, 'is_visible' => true, 'is_active' => true,
        ]);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'fields' => [$field->id => '<script>alert(1)</script>'],
        ]))->assertSessionHasErrors('fields.'.$field->id);

        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_field_values', 0);
    }

    public function test_attachments_are_private_and_other_requesters_cannot_open_the_ticket(): void
    {
        Storage::fake('local');
        $data = $this->baseConfiguration();
        $owner = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $stranger = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);

        $this->actingAs($owner)->post(route('tickets.store'), $this->payload($data, [
            'attachments' => [UploadedFile::fake()->create('evidencia.pdf', 120, 'application/pdf')],
        ]))->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $attachment = $ticket->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertNotSame('evidencia.pdf', basename($attachment->path));
        $this->assertSame(64, strlen($attachment->sha256));
        $this->actingAs($stranger)->get(route('tickets.show', $ticket))->assertForbidden();
        $this->actingAs($stranger)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertForbidden();
        $this->actingAs($owner)->get(route('tickets.attachments.download', [$ticket, $attachment]))->assertDownload('evidencia.pdf');
    }

    public function test_multilevel_approval_only_advances_for_the_authorized_approver(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $approverOne = $this->userWithPermissions(['tickets.view', 'tickets.approve']);
        $approverTwo = $this->userWithPermissions(['tickets.view', 'tickets.approve']);
        $intruder = $this->userWithPermissions(['tickets.view', 'tickets.approve']);
        $workflow = ApprovalWorkflow::create([
            'ticket_category_id' => $data['category']->id, 'ticket_subcategory_id' => $data['subcategory']->id,
            'code' => 'APR-2', 'name' => 'Aprobación de dos niveles', 'is_active' => true,
        ]);
        foreach ([1 => $approverOne, 2 => $approverTwo] as $number => $approver) {
            ApprovalLevel::create([
                'approval_workflow_id' => $workflow->id, 'level_number' => $number, 'name' => 'Nivel '.$number,
                'approver_type' => 'specific_user', 'approver_user_id' => $approver->id,
                'is_required' => true, 'allow_delegation' => true, 'is_active' => true,
            ]);
        }

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data))->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $first = $ticket->approvals()->where('level_number', 1)->firstOrFail();
        $this->assertSame('pending', $first->status);
        $this->assertSame('waiting', $ticket->approvals()->where('level_number', 2)->value('status'));

        $this->actingAs($intruder)->post(route('tickets.approvals.decide', [$ticket, $first]), [
            'decision' => 'approved', 'comment' => 'No autorizado',
        ])->assertForbidden();
        $this->actingAs($approverOne)->post(route('tickets.approvals.decide', [$ticket, $first]), [
            'decision' => 'approved', 'comment' => 'Primer nivel validado.',
        ])->assertRedirect();
        $this->assertDatabaseHas('ticket_approvals', ['ticket_id' => $ticket->id, 'level_number' => 2, 'status' => 'pending']);

        $second = $ticket->approvals()->where('level_number', 2)->firstOrFail();
        $this->actingAs($approverTwo)->post(route('tickets.approvals.decide', [$ticket, $second]), [
            'decision' => 'approved', 'comment' => 'Aprobación final.',
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'approval_status' => 'approved', 'current_assignee_id' => $data['technician']->id]);
    }

    public function test_technician_accepts_assignment_and_internal_notes_remain_private(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.comment'], $data['area']);
        $this->givePermissions($data['technician'], [
            'tickets.view', 'tickets.view_assigned', 'tickets.accept_assignment', 'tickets.internal_note', 'tickets.comment',
        ]);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data))->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $assignment = $ticket->assignments()->firstOrFail();
        $this->actingAs($data['technician'])->patch(route('tickets.assignments.respond', [$ticket, $assignment]), [
            'decision' => 'accepted',
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assignment_status' => 'accepted', 'ticket_status_id' => $data['statuses']['en-analisis']->id]);

        $secret = 'Diagnóstico interno reservado 9e42.';
        $this->actingAs($data['technician'])->post(route('tickets.notes.store', $ticket), ['body' => $secret])->assertRedirect();
        $this->actingAs($requester)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee($secret);
        $this->actingAs($data['technician'])->get(route('tickets.show', $ticket))->assertOk()->assertSee($secret);
    }

    public function test_solution_acceptance_closure_and_reopening_are_audited(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions([
            'tickets.view', 'tickets.create', 'tickets.comment', 'tickets.review', 'tickets.reopen',
        ], $data['area']);
        $this->givePermissions($data['technician'], ['tickets.view', 'tickets.view_assigned', 'tickets.solve']);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data))->assertRedirect();
        $ticket = Ticket::firstOrFail();

        $this->actingAs($data['technician'])->post(route('tickets.solution.store', $ticket), [
            'solution' => 'Se corrigió la configuración, se reinició el servicio y se validó el acceso correctamente.',
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'ticket_status_id' => $data['statuses']['solucionado']->id]);

        $this->actingAs($requester)->post(route('tickets.review.store', $ticket->fresh()), [
            'decision' => 'accepted', 'rating' => 5, 'comment' => 'Servicio satisfactorio.', 'confirmation' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'ticket_status_id' => $data['statuses']['cerrado']->id]);
        $this->assertDatabaseHas('ticket_reviews', ['ticket_id' => $ticket->id, 'decision' => 'accepted', 'rating' => 5]);

        $this->actingAs($requester)->patch(route('tickets.reopen', $ticket->fresh()), [
            'reason' => 'El inconveniente volvió a presentarse durante la operación.',
        ])->assertRedirect();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'ticket_status_id' => $data['statuses']['en-proceso']->id, 'reopen_count' => 1]);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'event' => 'solution_accepted']);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'event' => 'ticket_reopened']);
    }

    private function baseConfiguration(): array
    {
        $area = Area::create(['code' => 'OPE', 'name' => 'Operaciones', 'type' => 'organizational', 'is_active' => true]);
        $itArea = Area::create(['code' => 'IT-SOP', 'name' => 'Soporte', 'type' => 'it', 'is_active' => true]);
        $category = TicketCategory::create(['code' => 'SOP', 'name' => 'Soporte técnico', 'type' => 'request', 'is_active' => true]);
        $subcategory = TicketSubcategory::create([
            'ticket_category_id' => $category->id, 'code' => 'SFT', 'name' => 'Software', 'is_active' => true,
        ]);
        $impact = ImpactLevel::create(['name' => 'Medio', 'value' => 2, 'is_active' => true]);
        $urgency = UrgencyLevel::create(['name' => 'Media', 'value' => 2, 'is_active' => true]);
        $priority = TicketPriority::create(['code' => 'P3', 'name' => 'Media', 'color' => '#225C9A', 'weight' => 2, 'is_active' => true]);
        PriorityMatrixRule::create(['impact_level_id' => $impact->id, 'urgency_level_id' => $urgency->id, 'ticket_priority_id' => $priority->id]);
        ServiceLevelTarget::create([
            'ticket_priority_id' => $priority->id, 'response_minutes' => 120, 'escalation_minutes' => 480,
            'resolution_minutes' => 1440, 'business_hours_only' => false, 'is_active' => true,
        ]);

        $statuses = collect([
            ['Registrado', 'registrado', true, false], ['Pendiente de aprobación', 'pendiente-aprobacion', false, false],
            ['Asignado', 'asignado', false, false], ['En análisis', 'en-analisis', false, false],
            ['En proceso', 'en-proceso', false, false], ['Pendiente del usuario', 'pendiente-usuario', false, false],
            ['Solucionado', 'solucionado', false, false], ['Cerrado', 'cerrado', false, true],
            ['Cancelado', 'cancelado', false, true],
        ])->mapWithKeys(fn ($item, $index) => [$item[1] => TicketStatus::create([
            'name' => $item[0], 'slug' => $item[1], 'color' => '#225C9A', 'sort_order' => ($index + 1) * 10,
            'is_initial' => $item[2], 'is_final' => $item[3], 'is_active' => true,
        ])]);

        $technician = User::factory()->create();
        $group = TechnicalGroup::create(['area_id' => $itArea->id, 'code' => 'GRP-SOP', 'name' => 'Mesa de ayuda', 'is_active' => true]);
        $group->members()->attach($technician, ['membership_role' => 'member']);
        RoutingRule::create([
            'ticket_category_id' => $category->id, 'ticket_subcategory_id' => $subcategory->id,
            'it_area_id' => $itArea->id, 'it_group_id' => $group->id, 'assignee_user_id' => $technician->id,
            'name' => 'Soporte automático', 'precedence' => 1, 'is_active' => true,
        ]);

        return compact('area', 'itArea', 'category', 'subcategory', 'impact', 'urgency', 'priority', 'statuses', 'technician', 'group');
    }

    private function payload(array $data, array $overrides = []): array
    {
        return array_merge([
            'action' => 'submit', 'ticket_category_id' => $data['category']->id,
            'ticket_subcategory_id' => $data['subcategory']->id, 'title' => 'Error en aplicación institucional',
            'description' => 'La aplicación presenta un error al guardar la información de la operación.',
            'requester_area_id' => $data['area']->id, 'location' => 'Edificio administrativo',
            'attention_type' => 'remote', 'impact_level_id' => $data['impact']->id,
            'urgency_level_id' => $data['urgency']->id,
        ], $overrides);
    }

    private function userWithPermissions(array $slugs, ?Area $area = null): User
    {
        $user = User::factory()->create(['area_id' => $area?->id]);
        $this->givePermissions($user, $slugs);

        return $user;
    }

    private function givePermissions(User $user, array $slugs): void
    {
        $permissions = collect($slugs)->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], [
            'module' => 'Tickets', 'action' => $slug, 'name' => $slug, 'description' => $slug,
        ]));
        $role = Role::create(['name' => 'Perfil '.fake()->unique()->word(), 'slug' => 'perfil-'.fake()->unique()->slug(), 'is_active' => true]);
        $role->permissions()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);
        $user->unsetRelation('roles');
    }
}
