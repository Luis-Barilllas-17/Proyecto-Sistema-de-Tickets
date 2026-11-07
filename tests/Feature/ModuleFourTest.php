<?php

namespace Tests\Feature;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Area;
use App\Models\Department;
use App\Models\Equipment;
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
use App\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModuleFourTest extends TestCase
{
    use RefreshDatabase;

    public static function staffProfiles(): array
    {
        return ['administrador' => [true], 'tecnico' => [false]];
    }

    #[DataProvider('staffProfiles')]
    public function test_staff_has_only_the_staff_form_without_the_requester_questions(bool $superAdmin): void
    {
        $data = $this->baseConfiguration();
        $staff = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.create_internal'], $data['itArea']);
        $staff->update(['is_super_admin' => $superAdmin]);
        $form = $this->supportForm($data);

        $this->actingAs($staff)->get(route('tickets.create'))
            ->assertOk()->assertSee('¿Qué trabajo de IT vas a registrar?')->assertDontSee('¿Qué necesitas reportar?');
        $this->get(route('tickets.create', ['category_id' => $data['category']->id, 'subcategory_id' => $data['subcategory']->id]))
            ->assertOk()->assertSee('data-ticket-form="staff"', false)
            ->assertSee('Descripción técnica y alcance')->assertSee('Solicitante de la atención')
            ->assertDontSee($form->name)->assertDontSee('name="fields[', false)
            ->assertDontSee('type="checkbox" name="is_internal"', false);

        // El tipo de registro no cambia el formulario asignado por el servidor.
        $this->post(route('tickets.store'), $this->payload($data, ['is_internal' => 0]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('tickets', ['source' => 'requester', 'created_by_id' => $staff->id, 'ticket_form_id' => null]);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('ticket_field_values', 0);
    }

    public function test_requester_fills_subject_and_description_once_and_legacy_answers_are_kept_in_sync(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $form = $this->supportForm($data);
        $response = $this->actingAs($requester)->get(route('tickets.create', [
            'category_id' => $data['category']->id, 'subcategory_id' => $data['subcategory']->id,
        ]))->assertOk()->assertSee('data-ticket-form="requester"', false)
            ->assertDontSee('Solicitante de la atención')->assertDontSee('Registro interno de IT');

        foreach ($form->fields as $field) {
            $response->assertDontSee('name="fields['.$field->id.']"', false);
        }
        $this->assertSame(1, substr_count($response->getContent(), 'name="title"'));
        $this->assertSame(1, preg_match_all('/<textarea\b[^>]*\bname="description"/', $response->getContent()));

        $this->post(route('tickets.store'), $this->payload($data, [
            'fields' => $form->fields->mapWithKeys(fn ($field) => [$field->id => 'Valor duplicado manipulado'])->all(),
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $this->assertSame('requester', $ticket->source);
        foreach ($form->fields as $field) {
            $this->assertDatabaseHas('ticket_field_values', [
                'ticket_id' => $ticket->id, 'form_field_id' => $field->id,
                'value' => $field->name === 'asunto' ? $ticket->title : $ticket->description,
            ]);
        }
    }

    public function test_requester_cannot_enable_internal_capture_or_impersonate_another_requester(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, ['is_internal' => 1]))->assertForbidden();
        $this->assertDatabaseCount('tickets', 0);
        $this->post(route('tickets.store'), $this->payload($data, ['requester_id' => $data['technician']->id]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('tickets', ['requester_id' => $requester->id, 'source' => 'requester']);
    }

    public function test_staff_capture_respects_the_selected_area_and_preserves_creator(): void
    {
        $data = $this->baseConfiguration();
        $staff = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.create_internal'], $data['itArea']);
        $requester = User::factory()->create(['area_id' => $data['area']->id]);
        $this->actingAs($staff)->post(route('tickets.store'), $this->payload($data, [
            'requester_id' => $requester->id, 'requester_area_id' => $data['itArea']->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'requester_id' => $requester->id, 'created_by_id' => $staff->id,
            'requester_area_id' => $data['itArea']->id, 'source' => 'requester',
        ]);
    }

    public function test_failed_submission_does_not_leave_drafts_files_or_duplicate_numbers(): void
    {
        Storage::fake('local');
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        PriorityMatrixRule::query()->delete();
        $payload = $this->payload($data, ['attachments' => [UploadedFile::fake()->create('evidencia.txt', 1, 'text/plain')]]);

        $this->actingAs($requester)->post(route('tickets.store'), $payload)->assertSessionHasErrors('impact_level_id');
        $this->assertDatabaseCount('tickets', 0);
        $this->assertDatabaseCount('ticket_histories', 0);
        $this->assertDatabaseCount('ticket_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        PriorityMatrixRule::create(['impact_level_id' => $data['impact']->id, 'urgency_level_id' => $data['urgency']->id, 'ticket_priority_id' => $data['priority']->id]);
        $this->post(route('tickets.store'), $this->payload($data))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(sprintf('ZT-%d-000001', now()->year), Ticket::firstOrFail()->number);
    }

    public function test_failed_resubmission_preserves_the_original_draft(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, ['action' => 'draft']))->assertRedirect();
        $ticket = Ticket::firstOrFail();
        PriorityMatrixRule::query()->delete();
        $this->put(route('tickets.update', $ticket), $this->payload($data, [
            'title' => 'Cambio que no debe persistir', 'lock_version' => $ticket->lock_version,
        ]))->assertSessionHasErrors('impact_level_id');
        $this->assertSame('Error en aplicación institucional', $ticket->fresh()->title);
        $this->assertTrue($ticket->fresh()->is_draft);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_internal_draft_retains_its_form_mode_and_legacy_custom_answers(): void
    {
        $data = $this->baseConfiguration();
        $staff = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.create_internal'], $data['itArea']);
        $form = $this->supportForm($data);
        $this->actingAs($staff)->post(route('tickets.store'), $this->payload($data, ['action' => 'draft', 'is_internal' => 1]))->assertRedirect();
        $ticket = Ticket::firstOrFail();
        // Simula un borrador anterior a la separación de formularios.
        $ticket->update(['ticket_form_id' => $form->id]);
        $field = $form->fields->first();
        $ticket->fieldValues()->create(['form_field_id' => $field->id, 'value' => 'Respuesta histórica conservada']);
        $this->get(route('tickets.edit', $ticket))->assertOk()->assertSee('data-ticket-form="staff"', false)->assertDontSee($form->name);
        $this->put(route('tickets.update', $ticket), $this->payload($data, [
            'action' => 'draft', 'lock_version' => $ticket->lock_version,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('ticket_field_values', ['ticket_id' => $ticket->id, 'form_field_id' => $field->id, 'value' => 'Respuesta histórica conservada']);
        $this->assertSame('it_internal', $ticket->fresh()->source);
    }

    public function test_file_question_uses_the_single_attachment_input_and_still_requires_evidence(): void
    {
        Storage::fake('local');
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $form = $this->supportForm($data);
        $field = $form->fields()->create([
            'name' => 'evidencia', 'label' => 'Evidencia del incidente', 'type' => 'file',
            'is_required' => true, 'is_visible' => true, 'is_active' => true,
        ]);
        $response = $this->actingAs($requester)->get(route('tickets.create', [
            'category_id' => $data['category']->id, 'subcategory_id' => $data['subcategory']->id,
        ]))->assertOk()->assertSee('Evidencia del incidente (obligatorio al enviar)');
        $this->assertSame(1, substr_count($response->getContent(), 'type="file"'));
        $this->post(route('tickets.store'), $this->payload($data))->assertSessionHasErrors('fields.'.$field->id);
        $this->post(route('tickets.store'), $this->payload($data, [
            'attachments' => [UploadedFile::fake()->create('evidencia.txt', 1, 'text/plain')],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('ticket_attachments', 1);
    }

    public function test_missing_subcategory_does_not_open_an_incomplete_new_form(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $this->actingAs($requester)->get(route('tickets.create', ['category_id' => $data['category']->id]))
            ->assertOk()->assertSee('data-classification-form', false)->assertDontSee('data-ticket-form=', false);
        $this->post(route('tickets.store'), $this->payload($data, ['ticket_subcategory_id' => null]))->assertSessionHasErrors('ticket_subcategory_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_staff_request_can_be_edited_and_accepted_by_its_requester_without_exposing_other_users(): void
    {
        $data = $this->baseConfiguration();
        $staff = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.create_internal', 'tickets.solve'], $data['itArea']);
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.review'], $data['area']);
        $this->supportForm($data);
        $this->actingAs($staff)->post(route('tickets.store'), $this->payload($data, [
            'action' => 'draft', 'requester_id' => $requester->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $this->get(route('tickets.edit', $ticket))->assertOk()->assertSee('data-ticket-form="staff"', false);
        $this->actingAs($requester)->get(route('tickets.edit', $ticket))->assertOk()
            ->assertSee('data-ticket-form="requester"', false)->assertDontSee('name="requester_id"', false)
            ->assertDontSee($staff->email)->assertDontSee('name="fields[', false);
        $this->put(route('tickets.update', $ticket), $this->payload($data, [
            'lock_version' => $ticket->lock_version, 'is_internal' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id, 'source' => 'requester', 'created_by_id' => $staff->id, 'requester_id' => $requester->id,
        ]);
        $this->actingAs($staff)->post(route('tickets.solution.store', $ticket->fresh()), [
            'solution' => 'Se corrigió la configuración del cliente y se validó el guardado de registros con el usuario.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($requester)->post(route('tickets.review.store', $ticket->fresh()), [
            'decision' => 'accepted', 'rating' => 5, 'confirmation' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('cerrado', $ticket->fresh()->status->slug);
    }

    public function test_area_and_department_can_differ_from_the_requester_profile(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $department = Department::create(['area_id' => $data['itArea']->id, 'code' => 'QA-SOP', 'name' => 'Soporte QA', 'is_active' => true]);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'requester_area_id' => $data['itArea']->id, 'requester_department_id' => $department->id,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $this->assertSame($data['itArea']->id, $ticket->requester_area_id);
        $this->assertSame($department->id, $ticket->requester_department_id);
        $this->assertSame($data['area']->id, $requester->fresh()->area_id);
    }

    public function test_department_from_another_area_is_rejected_before_saving(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $department = Department::create(['area_id' => $data['itArea']->id, 'code' => 'QA-OTHER', 'name' => 'Otra área', 'is_active' => true]);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'requester_department_id' => $department->id,
        ]))->assertSessionHasErrors('requester_department_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_multiple_registered_equipment_can_be_linked_and_removed_from_a_draft(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $one = $this->registeredEquipment('QA-PC-001');
        $two = $this->registeredEquipment('QA-PC-002');
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'action' => 'draft', 'equipment_ids' => [$one->id, $two->id],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $firstLink = $ticket->equipmentLinks()->where('equipment_id', $one->id)->firstOrFail();
        $this->assertDatabaseCount('ticket_equipment_links', 2);
        $this->get(route('tickets.edit', $ticket))->assertOk()->assertSee('QA-PC-001')->assertSee('QA-PC-002')
            ->assertDontSee('name="equipment_codes"', false);
        $one->update(['status' => 'retired', 'is_active' => false]);
        $this->put(route('tickets.update', $ticket), $this->payload($data, [
            'action' => 'draft', 'equipment_ids' => [$one->id], 'lock_version' => $ticket->lock_version,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('ticket_equipment_links', 1);
        $this->assertDatabaseHas('ticket_equipment_links', ['id' => $firstLink->id, 'equipment_id' => $one->id, 'inventory_code' => $one->inventory_code, 'equipment_name' => $one->name]);
    }

    public function test_unregistered_duplicate_retired_and_manually_typed_equipment_are_rejected(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $asset = $this->registeredEquipment('QA-PC-VALID');
        $retired = $this->registeredEquipment('QA-PC-RETIRED', ['status' => 'retired', 'is_active' => false]);
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, ['equipment_ids' => [999999]]))
            ->assertSessionHasErrors('equipment_ids.0');
        $this->post(route('tickets.store'), $this->payload($data, ['equipment_ids' => [$asset->id, $asset->id]]))
            ->assertSessionHasErrors('equipment_ids.0');
        $this->post(route('tickets.store'), $this->payload($data, ['equipment_ids' => [$retired->id]]))
            ->assertSessionHasErrors('equipment_ids');
        $this->post(route('tickets.store'), $this->payload($data, ['equipment_codes' => 'NO-REGISTRADO | Nombre inventado']))
            ->assertSessionHasErrors('equipment_codes');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_equipment_limit_and_legacy_reference_ownership_are_enforced(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);
        $ids = [];
        foreach (range(1, 11) as $number) {
            $ids[] = $this->registeredEquipment('QA-LIMIT-'.$number)->id;
        }
        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, ['equipment_ids' => $ids]))
            ->assertSessionHasErrors('equipment_ids');
        $this->post(route('tickets.store'), $this->payload($data, ['action' => 'draft']))->assertSessionHasNoErrors();
        $ticket = Ticket::firstOrFail();
        $legacy = $ticket->equipmentLinks()->create(['inventory_code' => 'LEGACY-01', 'equipment_name' => 'Referencia histórica', 'linked_by_id' => $requester->id]);
        $this->put(route('tickets.update', $ticket), $this->payload($data, [
            'action' => 'draft', 'legacy_equipment_link_ids' => [$legacy->id], 'lock_version' => $ticket->lock_version,
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_equipment_links', ['id' => $legacy->id, 'inventory_code' => 'LEGACY-01']);
        $this->post(route('tickets.store'), $this->payload($data, ['legacy_equipment_link_ids' => [$legacy->id]]))
            ->assertSessionHasErrors('legacy_equipment_link_ids.0');
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_picker_lookups_are_filtered_limited_and_respect_permissions(): void
    {
        $data = $this->baseConfiguration();
        $staff = $this->userWithPermissions(['tickets.view', 'tickets.create', 'tickets.create_internal']);
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create']);
        User::factory()->count(24)->create(['name' => 'Coincidencia QA']);
        $inactive = User::factory()->create(['name' => 'Coincidencia QA inactiva', 'status' => 'inactive']);
        $asset = $this->registeredEquipment('QA-LOOKUP', ['ip_address' => '192.0.2.10', 'acquisition_cost' => 1500]);
        $retired = $this->registeredEquipment('QA-RETIRED', ['status' => 'retired']);

        $this->actingAs($staff)->getJson(route('tickets.lookups.requesters', ['q' => 'Coincidencia']))
            ->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('has_more', true)->assertDontSee($inactive->email);
        $this->getJson(route('tickets.lookups.requesters', ['q' => $staff->email]))
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.value', (string) $staff->id);
        $this->actingAs($requester)->getJson(route('tickets.lookups.requesters'))->assertForbidden();
        $this->getJson(route('tickets.lookups.equipment', ['q' => 'QA-LOOKUP']))
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.value', (string) $asset->id)
            ->assertDontSee('ip_address')->assertDontSee('acquisition_cost');
        $this->getJson(route('tickets.lookups.equipment', ['q' => $retired->inventory_code]))->assertOk()->assertJsonCount(0, 'results');
        $this->getJson(route('tickets.lookups.requesters', ['q' => str_repeat('a', 101)]))->assertForbidden();
        $this->actingAs($staff)->getJson(route('tickets.lookups.requesters', ['q' => str_repeat('a', 101)]))->assertUnprocessable();
    }

    private function registeredEquipment(string $code, array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'inventory_code' => $code, 'name' => 'Equipo '.$code, 'asset_type' => 'computer',
            'location' => 'Oficina QA', 'status' => 'available', 'is_active' => true,
            'created_by_id' => User::query()->value('id'),
        ], $overrides));
    }

    private function supportForm(array $data): TicketForm
    {
        $form = TicketForm::create([
            'ticket_category_id' => $data['category']->id, 'code' => 'FORM-SOP-QA',
            'name' => 'Formulario de solicitante para soporte', 'is_active' => true,
        ]);
        foreach ([['asunto', 'Asunto', 'text'], ['descripcion', 'Descripción del problema', 'textarea']] as [$name, $label, $type]) {
            $form->fields()->create([
                'name' => $name, 'label' => $label, 'type' => $type,
                'is_required' => true, 'is_visible' => true, 'is_active' => true,
            ]);
        }

        return $form->load('fields');
    }

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

    public function test_submitted_ticket_calculates_priority_sla_and_waits_for_manual_assignment(): void
    {
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data))->assertRedirect();
        $ticket = Ticket::firstOrFail();

        $this->assertFalse($ticket->is_draft);
        $this->assertSame($data['priority']->id, $ticket->ticket_priority_id);
        $this->assertNull($ticket->current_it_group_id);
        $this->assertNull($ticket->current_assignee_id);
        $this->assertSame('unassigned', $ticket->assignment_status);
        $this->assertNotNull($ticket->response_due_at);
        $this->assertDatabaseMissing('ticket_assignments', ['ticket_id' => $ticket->id]);
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

    public function test_attachment_larger_than_the_safe_limit_is_rejected(): void
    {
        Storage::fake('local');
        $data = $this->baseConfiguration();
        $requester = $this->userWithPermissions(['tickets.view', 'tickets.create'], $data['area']);

        $this->actingAs($requester)->post(route('tickets.store'), $this->payload($data, [
            'attachments' => [UploadedFile::fake()->create('demasiado-grande.pdf', 5121, 'application/pdf')],
        ]))->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('tickets', 0);
        Storage::disk('local')->assertMissing('ticket-attachments');
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
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'approval_status' => 'approved', 'current_assignee_id' => null, 'assignment_status' => 'unassigned']);
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
        app(TicketWorkflowService::class)->assign($ticket, $requester, $data['itArea']->id, $data['group']->id, $data['technician']->id, 'Asignación manual de prueba.');
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
        app(TicketWorkflowService::class)->assign($ticket, $requester, $data['itArea']->id, $data['group']->id, $data['technician']->id, 'Asignación manual de prueba.');

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
