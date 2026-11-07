<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketReopenSolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_reopen_returns_to_attention_opens_editor_and_invalidates_the_previous_solution(): void
    {
        [$ticket] = $this->closedWithStock();
        $previous = $ticket->solution;
        $this->actingAs($this->admin())->patch(route('tickets.reopen', $ticket), [
            'reason' => 'El problema se presentó nuevamente y requiere actualización.', 'stock_action' => 'return',
        ])->assertRedirect(route('tickets.show', $ticket).'#ticket-resolution')->assertSessionHas('edit_solution', true);
        $ticket->refresh();
        $this->assertSame('en-proceso', $ticket->status->slug);
        $this->assertNull($ticket->solved_at);
        $this->assertSame($previous, $ticket->solution);
        $this->assertTrue($ticket->solutionNeedsUpdate());
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Actualizar solución tras reapertura')
            ->assertSee('data-auto-edit="true"', false)->assertSee('Reservar de nuevo')->assertDontSee('data-star-rating', false);
        $history = $ticket->histories()->where('event', 'ticket_reopened')->latest('id')->firstOrFail();
        $this->assertSame($previous, $history->from_values['solution']);
        // Even a stale/manually altered status must not make the previous solution acceptable.
        $ticket->update(['ticket_status_id' => TicketStatus::where('slug', 'solucionado')->value('id')]);
        $this->postJson(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 5, 'confirmation' => 1])->assertForbidden();
    }

    public function test_return_reserve_update_accept_cycle_consumes_only_the_new_reservation_once(): void
    {
        [$ticket, $supply, $starting] = $this->closedWithStock();
        $this->actingAs($this->admin())->patch(route('tickets.reopen', $ticket), [
            'reason' => 'Se recibieron los materiales sin usar y se revisará de nuevo.', 'stock_action' => 'return',
        ])->assertSessionHasNoErrors();
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), [
            'supply_id' => $supply->id, 'quantity' => 1, 'notes' => 'Un conector para la nueva intervención.',
        ])->assertSessionHasNoErrors();
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->post(route('tickets.solution.store', $ticket), [
            'solution' => 'Nueva revisión: se cambió un conector y se validó la conectividad.',
        ])->assertSessionHasNoErrors();
        $ticket->refresh();
        $this->assertSame('solucionado', $ticket->status->slug);
        $this->assertFalse($ticket->solutionNeedsUpdate());
        $this->assertTrue($ticket->solved_at->gte($ticket->reopened_at));
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'status' => 'reserved', 'quantity_reserved' => 1]);
        $this->actingAs($ticket->requester)->post(route('tickets.review.store', $ticket), [
            'decision' => 'accepted', 'rating' => 5, 'confirmation' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($starting - 1, (float) $supply->fresh()->stock_quantity);
        $this->postJson(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 5, 'confirmation' => 1])->assertForbidden();
        $this->assertSame($starting - 1, (float) $supply->fresh()->stock_quantity);
        $this->assertSame(2, $supply->movements()->where('ticket_id', $ticket->id)->where('movement_type', 'consumption')->count());
        $this->assertSame(1, $supply->movements()->where('ticket_id', $ticket->id)->where('movement_type', 'return')->count());
    }

    public function test_deleting_solution_preserves_history_evidence_and_reserved_stock(): void
    {
        $ticket = $this->ticket();
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        $starting = (float) $supply->stock_quantity;
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), ['supply_id' => $supply->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $solution = 'Se comprobó el enlace y se documentó una solución con evidencia.';
        $this->post(route('tickets.solution.store', $ticket), ['solution' => $solution,
            'attachments' => [UploadedFile::fake()->createWithContent('evidencia.txt', 'Evidencia de diagnóstico técnico.')],
        ])->assertSessionHasNoErrors();
        $file = $ticket->attachments()->latest('id')->firstOrFail();
        $this->delete(route('tickets.solution.destroy', $ticket))->assertRedirect(route('tickets.show', $ticket).'#ticket-resolution');
        $ticket->refresh();
        $this->assertNull($ticket->solution);
        $this->assertNull($ticket->solution_by_id);
        $this->assertNull($ticket->solved_at);
        $this->assertSame('en-proceso', $ticket->status->slug);
        $this->assertSame($starting, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'status' => 'reserved']);
        Storage::disk($file->disk)->assertExists($file->path);
        $history = $ticket->histories()->where('event', 'solution_deleted')->firstOrFail();
        $this->assertSame($solution, $history->from_values['solution']);
        $this->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('data-star-rating', false)->assertDontSee('data-delete-solution', false);
        $this->deleteJson(route('tickets.solution.destroy', $ticket))->assertForbidden();
    }

    public function test_deletion_is_forbidden_without_permission_and_when_closed(): void
    {
        [$ticket, $supply, $starting] = $this->closedWithStock();
        $this->actingAs($this->admin())->deleteJson(route('tickets.solution.destroy', $ticket))->assertForbidden();
        $this->assertSame($starting - 2, (float) $supply->fresh()->stock_quantity);
        $this->patch(route('tickets.reopen', $ticket), ['reason' => 'Se requiere corregir el diagnóstico de la solución.', 'stock_action' => 'keep'])->assertSessionHasNoErrors();
        $this->actingAs($ticket->requester)->deleteJson(route('tickets.solution.destroy', $ticket))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('tickets.solution.destroy', $ticket))->assertSessionHasNoErrors();
        $this->assertSame($starting - 2, (float) $supply->fresh()->stock_quantity);
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'status' => 'consumed']);
    }

    public function test_group_assignment_without_a_person_displays_assign_not_reassign(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['current_assignee_id' => null, 'assignment_status' => 'group_assigned']);
        $this->actingAs($this->admin())->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('Sin técnico asignado')->assertSee('Asignar técnico')->assertDontSee('Reasignar técnico');
    }

    private function closedWithStock(): array
    {
        $ticket = $this->ticket();
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        $starting = (float) $supply->stock_quantity;
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), ['supply_id' => $supply->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(route('tickets.solution.store', $ticket), ['solution' => 'Se comprobó el enlace y se resolvió la incidencia de conectividad.'])->assertSessionHasNoErrors();
        $this->actingAs($ticket->requester)->post(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 4, 'confirmation' => 1])->assertSessionHasNoErrors();

        return [$ticket->fresh(), $supply, $starting];
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
