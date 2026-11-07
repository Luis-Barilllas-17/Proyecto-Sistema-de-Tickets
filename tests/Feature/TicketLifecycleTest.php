<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_even_admin_cannot_rate_without_a_real_solution_or_reopen_an_open_ticket(): void
    {
        $ticket = $this->ticket();
        foreach (['en-proceso', 'solucionado'] as $slug) {
            $ticket->update(['solution' => null, 'ticket_status_id' => TicketStatus::where('slug', $slug)->value('id')]);
            $this->actingAs($this->admin())->get(route('tickets.show', $ticket))->assertOk()
                ->assertDontSee('data-star-rating', false)->assertSee('data-solution-editor', false)->assertDontSee('Reabrir atención');
            $this->postJson(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 5, 'confirmation' => 1])->assertForbidden();
            $this->patchJson(route('tickets.reopen', $ticket), ['reason' => 'Intento de reapertura de un ticket abierto.'])->assertForbidden();
        }
    }

    public function test_closed_tickets_are_read_only_even_for_admin_and_reject_direct_mutation_requests(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['ticket_status_id' => TicketStatus::where('slug', 'cerrado')->value('id'), 'solution' => 'Solución registrada y aceptada por el solicitante.']);
        $this->actingAs($this->admin())->get(route('tickets.show', $ticket))->assertOk()->assertSee('Solo lectura')
            ->assertSee('Reabrir atención')->assertDontSee('data-star-rating', false)->assertDontSee('data-solution-editor', false)
            ->assertDontSee('data-message-composer', false)->assertDontSee('data-assignment-form', false)->assertDontSee('ticket-supply-search', false);
        foreach (['solution.store', 'comments.store', 'notes.store', 'activities.store', 'relations.store', 'assignments.store', 'assignments.transfer', 'supplies.store', 'third-parties.store', 'escalations.store', 'review.store'] as $route) {
            $this->postJson(route('tickets.'.$route, $ticket), [])->assertForbidden();
        }
        $this->patchJson(route('tickets.status', $ticket), [])->assertForbidden();
        $this->patchJson(route('tickets.cancel', $ticket), [])->assertForbidden();
        $this->putJson(route('tickets.update', $ticket), [])->assertForbidden();
        $this->assertSame('cerrado', $ticket->fresh()->status->slug);
    }

    public function test_one_composer_posts_public_or_private_notes_without_leaking_internal_files(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->staff())->post(route('tickets.comments.store', $ticket), [
            'body' => 'Comentario visible de seguimiento.', 'is_internal' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('tickets.comments.store', $ticket), [
            'body' => 'Diagnóstico privado QA-INTERNO-872.', 'is_internal' => 1,
            'attachments' => [UploadedFile::fake()->createWithContent('diagnostico.txt', 'Detalle interno de prueba.')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'body' => 'Diagnóstico privado QA-INTERNO-872.', 'is_internal' => true]);
        $attachment = $ticket->attachments()->latest('id')->firstOrFail();
        $this->assertTrue($attachment->is_internal);
        $this->actingAs($ticket->requester)->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('Comentario visible de seguimiento.')->assertDontSee('QA-INTERNO-872')->assertDontSee('data-message-private', false);
        $this->getJson(route('tickets.attachments.preview', [$ticket, $attachment]))->assertForbidden();
        $this->postJson(route('tickets.comments.store', $ticket), ['body' => 'Nota interna falsificada.', 'is_internal' => 1])->assertForbidden();
        $html = $this->actingAs($this->staff())->get(route('tickets.show', $ticket))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-message-composer'));
        $this->assertStringNotContainsString('internal-note-body', $html);
        $this->assertSame(1, substr_count($html, 'data-confirm-dialog'));
    }

    public function test_submit_preserves_priority_and_sla_but_does_not_assign_the_requester_or_a_routing_technician(): void
    {
        $ticket = $this->ticket();
        $new = $ticket->replicate();
        $new->fill(['number' => 'QA-MANUAL', 'is_draft' => true, 'current_assignee_id' => null,
            'current_it_area_id' => null, 'current_it_group_id' => null]);
        $new->save();
        app(TicketWorkflowService::class)->submit($new, $new->requester);
        $new->refresh();
        $this->assertFalse($new->is_draft);
        $this->assertNull($new->current_assignee_id);
        $this->assertNull($new->current_it_group_id);
        $this->assertNull($new->current_it_area_id);
        $this->assertSame('unassigned', $new->assignment_status);
        $this->assertNotNull($new->ticket_priority_id);
        $this->assertNotNull($new->resolution_due_at);
        $this->assertDatabaseMissing('ticket_assignments', ['ticket_id' => $new->id]);
    }

    public function test_manual_assignment_requires_a_real_technician_and_reassignment_uses_the_same_card(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['current_assignee_id' => null, 'current_it_area_id' => null, 'current_it_group_id' => null]);
        $this->actingAs($this->admin())->get(route('tickets.show', $ticket))->assertOk()->assertSee('Asignar técnico')->assertDontSee('Reasignar técnico');
        $target = ['assignee_id' => $this->staff()->id, 'area_id' => $this->staff()->area_id, 'reason' => 'Asignación manual para atención.'];
        $this->postJson(route('tickets.assignments.store', $ticket), array_replace($target, ['assignee_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->postJson(route('tickets.assignments.store', $ticket), array_replace($target, ['assignee_id' => $ticket->requester_id]))->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->post(route('tickets.assignments.store', $ticket), $target)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->staff()->id, $ticket->fresh()->current_assignee_id);
        $this->assertSame('pending', $ticket->fresh()->assignment_status);
        $html = $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Reasignar técnico')->getContent();
        $this->assertSame(1, substr_count($html, 'data-assignment-form'));
        $this->postJson(route('tickets.assignments.store', $ticket), $target)->assertForbidden();
        $this->post(route('tickets.assignments.transfer', $ticket), array_replace($target, ['reason' => 'Reasignación de seguimiento por el administrador.']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_assignments', ['ticket_id' => $ticket->id, 'action' => 'transferred']);
    }

    public function test_solution_editor_is_in_main_content_is_audited_and_cannot_change_closed_ticket(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->staff())->post(route('tickets.solution.store', $ticket), ['solution' => 'Se corrigió la conexión y se comprobó el acceso correctamente.'])->assertSessionHasNoErrors();
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Editar solución')->assertSee('data-solution-editor', false);
        $this->post(route('tickets.solution.store', $ticket), ['solution' => 'Se actualizó el diagnóstico y se validó nuevamente la conexión.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'event' => 'solution_updated']);
        $ticket->refresh();
        $this->actingAs($ticket->requester)->get(route('tickets.show', $ticket))->assertOk()->assertSee('data-star-rating', false)->assertDontSee('Editar solución');
        $this->post(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 5, 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->postJson(route('tickets.solution.store', $ticket), ['solution' => 'No se debe permitir modificar esta solución ya cerrada.'])->assertForbidden();
    }

    public function test_reopen_keeps_consumption_by_default_and_does_not_deduct_it_twice(): void
    {
        [$ticket, $supply, $starting] = $this->closedWithConsumedStock();
        $this->actingAs($ticket->requester)->patch(route('tickets.reopen', $ticket), ['reason' => 'El problema se presentó nuevamente en la operación.', 'stock_action' => 'keep'])->assertSessionHasNoErrors();
        $this->assertSame($starting - 2, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'status' => 'consumed']);
        $this->closeAgain($ticket);
        $this->assertSame($starting - 2, (float) $supply->fresh()->stock_quantity);
        $this->assertSame(1, $supply->movements()->where('ticket_id', $ticket->id)->where('movement_type', 'consumption')->count());
    }

    public function test_authorized_reopen_returns_stock_once_records_kardex_and_can_reserve_again(): void
    {
        [$ticket, $supply, $starting] = $this->closedWithConsumedStock();
        $payload = ['reason' => 'Se recibieron físicamente los conectores sin uso.', 'stock_action' => 'return'];
        $this->actingAs($this->admin())->patch(route('tickets.reopen', $ticket), $payload)->assertSessionHasNoErrors();
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'status' => 'returned']);
        $this->assertDatabaseHas('supply_movements', ['ticket_id' => $ticket->id, 'movement_type' => 'return', 'quantity_delta' => 2]);
        $this->patchJson(route('tickets.reopen', $ticket), $payload)->assertForbidden();
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->post(route('tickets.supplies.store', $ticket), ['supply_id' => $supply->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->closeAgain($ticket);
        $this->assertSame($starting - 1, (float) $supply->fresh()->stock_quantity);
        $this->assertSame(2, $supply->movements()->where('ticket_id', $ticket->id)->where('movement_type', 'consumption')->count());
    }

    public function test_requester_cannot_forge_inventory_return_on_reopen(): void
    {
        [$ticket, $supply, $starting] = $this->closedWithConsumedStock();
        $this->actingAs($ticket->requester)->patchJson(route('tickets.reopen', $ticket), [
            'reason' => 'Intento de cambiar el inventario sin permisos.', 'stock_action' => 'return',
        ])->assertForbidden();
        $this->assertSame('cerrado', $ticket->fresh()->status->slug);
        $this->assertSame($starting - 2, (float) $supply->fresh()->stock_quantity);
    }

    public function test_related_lookup_searches_number_or_title_and_never_exposes_hidden_or_draft_tickets(): void
    {
        $ticket = $this->ticket();
        $visible = $ticket->replicate();
        $visible->fill(['number' => 'QA-VISIBLE-984', 'title' => 'Equipo de prueba búsqueda única']);
        $visible->save();
        $hidden = $visible->replicate();
        $hidden->fill(['number' => 'QA-OCULTO-984', 'requester_id' => $this->admin()->id, 'created_by_id' => $this->admin()->id,
            'current_assignee_id' => null, 'current_it_group_id' => null]);
        $hidden->save();
        $draft = $visible->replicate();
        $draft->fill(['number' => 'QA-DRAFT-984', 'is_draft' => true]);
        $draft->save();
        $this->actingAs($this->staff())->getJson(route('tickets.lookups.related', [$ticket, 'q' => '984']))->assertOk()
            ->assertJsonCount(1, 'results')->assertJsonPath('results.0.value', (string) $visible->id);
        $this->getJson(route('tickets.lookups.related', [$ticket, 'q' => 'búsqueda única']))->assertJsonCount(1, 'results');
        $this->getJson(route('tickets.lookups.related', [$ticket, 'q' => $ticket->number]))->assertJsonCount(0, 'results');
        $this->getJson(route('tickets.lookups.related', [$ticket, 'q' => str_repeat('x', 101)]))->assertUnprocessable();
    }

    private function closedWithConsumedStock(): array
    {
        $ticket = $this->ticket();
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        $starting = (float) $supply->stock_quantity;
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), ['supply_id' => $supply->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->closeAgain($ticket);

        return [$ticket->fresh(), $supply, $starting];
    }

    private function closeAgain(Ticket $ticket): void
    {
        $this->actingAs($this->staff())->post(route('tickets.solution.store', $ticket), ['solution' => 'Se reparó la conexión y se comprobó el funcionamiento del equipo.'])->assertSessionHasNoErrors();
        $this->actingAs($ticket->requester)->post(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 4, 'confirmation' => 1])->assertSessionHasNoErrors();
    }

    private function ticket(): Ticket
    {
        return Ticket::with('requester')->where('current_assignee_id', $this->staff()->id)->where('is_draft', false)
            ->whereNotIn('approval_status', ['pending', 'rejected', 'changes_requested'])
            ->whereHas('status', fn ($query) => $query->whereIn('slug', ['asignado', 'en-analisis', 'en-proceso']))->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('email', 'soporte@zolicguate.com')->firstOrFail();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@zolicguate.com')->firstOrFail();
    }
}
