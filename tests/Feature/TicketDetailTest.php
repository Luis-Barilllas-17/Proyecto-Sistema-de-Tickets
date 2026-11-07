<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class TicketDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_ticket_supply_reservations_accept_only_positive_whole_numbers(): void
    {
        $ticket = $this->ticket();
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        foreach (['1.5', '0', '-1', '1e1', '10000000000'] as $quantity) {
            $this->actingAs($this->staff())->postJson(route('tickets.supplies.store', $ticket), [
                'supply_id' => $supply->id, 'quantity' => $quantity,
            ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->assertDatabaseMissing('ticket_supply_usages', ['ticket_id' => $ticket->id]);
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), [
            'supply_id' => $supply->id, 'quantity' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_supply_usages', ['ticket_id' => $ticket->id, 'quantity_reserved' => 2]);
    }

    public function test_supply_lookup_filters_and_accounts_for_reservations_without_losing_current_reservation(): void
    {
        $ticket = $this->ticket();
        $supply = Supply::where('code', 'INS-RJ45')->firstOrFail();
        $supply->update(['stock_quantity' => 4.75]);
        $this->actingAs($this->staff())->post(route('tickets.supplies.store', $ticket), [
            'supply_id' => $supply->id, 'quantity' => 4,
        ])->assertSessionHasNoErrors();
        $this->getJson(route('tickets.lookups.supplies', [$ticket, 'q' => 'RJ45']))->assertOk()
            ->assertJsonCount(1, 'results')->assertJsonPath('results.0.available', 4)
            ->assertJsonPath('results.0.value', (string) $supply->id);
        $other = $ticket->replicate();
        $other->number = 'QA-OTHER';
        $other->save();
        $this->getJson(route('tickets.lookups.supplies', [$other, 'q' => 'RJ45']))->assertOk()->assertJsonCount(0, 'results');
        $this->getJson(route('tickets.lookups.supplies', [$ticket, 'q' => 'zzzzzz']))->assertJsonCount(0, 'results');
        $supply->update(['is_active' => false]);
        $this->getJson(route('tickets.lookups.supplies', [$ticket, 'q' => 'RJ45']))->assertJsonCount(0, 'results');
        $this->actingAs($ticket->requester)->getJson(route('tickets.lookups.supplies', $ticket))->assertForbidden();
        $hidden = $ticket->replicate();
        $hidden->number = 'QA-HIDDEN';
        $hidden->current_assignee_id = null;
        $hidden->current_it_group_id = null;
        $hidden->save();
        $this->actingAs($this->staff())->getJson(route('tickets.lookups.supplies', $hidden))->assertForbidden();
    }

    public function test_supply_search_is_bounded_and_searches_by_name(): void
    {
        for ($index = 0; $index < 25; $index++) {
            $supply = Supply::firstOrFail()->replicate();
            $supply->fill(['code' => 'QA-'.$index, 'name' => 'Material buscable '.$index, 'stock_quantity' => 5, 'is_active' => true]);
            $supply->save();
        }
        $this->actingAs($this->staff())->getJson(route('tickets.lookups.supplies', [$this->ticket(), 'q' => 'buscable']))
            ->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('has_more', true);
    }

    public function test_detail_contains_search_whole_quantity_stars_and_modal_links(): void
    {
        $ticket = $this->ticket();
        $attachment = $this->attachment($ticket, 'evidencia.txt', 'Detalle de prueba');
        $this->actingAs($this->staff())->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('ticket-supply-search', false)->assertSee('step="1"', false)
            ->assertSee('data-attachment-dialog', false)->assertSee(route('tickets.attachments.preview', [$ticket, $attachment]), false);
        $ticket->update(['ticket_status_id' => TicketStatus::where('slug', 'solucionado')->value('id'), 'solution' => 'Se verificó y corrigió la conexión de red del equipo.']);
        $this->actingAs($ticket->requester)->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('data-star-rating', false)->assertSee('type="radio" name="rating" value="5"', false)
            ->assertDontSee('<select id="rating"', false)->assertDontSee('ticket-supply-search', false);
    }

    public function test_preview_and_download_share_ticket_and_internal_file_authorization(): void
    {
        $ticket = $this->ticket();
        $attachment = $this->attachment($ticket, 'nota.txt', '<script>alert("prueba")</script>');
        $preview = route('tickets.attachments.preview', [$ticket, $attachment]);
        $this->getJson($preview)->assertUnauthorized();
        $this->actingAs($ticket->requester)->getJson($preview)->assertOk()->assertJsonPath('type', 'text')
            ->assertJsonPath('text', '<script>alert("prueba")</script>')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('tickets.attachments.inline', [$ticket, $attachment]))->assertStatus(415);
        $attachment->update(['is_internal' => true]);
        foreach (['preview', 'inline', 'download'] as $action) {
            $this->get(route('tickets.attachments.'.$action, [$ticket, $attachment]))->assertForbidden();
        }
        $this->actingAs($this->staff())->getJson($preview)->assertOk();
        $other = Ticket::whereKeyNot($ticket->id)->firstOrFail();
        $this->getJson(route('tickets.attachments.preview', [$other, $attachment]))->assertNotFound();
        $outsider = User::where('email', 'temporal@zolicguate.com')->firstOrFail();
        $outsider->update(['status' => 'active']);
        $this->actingAs($outsider)->getJson($preview)->assertForbidden();
        Storage::disk('local')->delete($attachment->path);
        $this->actingAs($this->staff())->getJson($preview)->assertNotFound();
    }

    public function test_images_and_pdf_are_private_inline_and_only_content_route_can_be_framed(): void
    {
        $ticket = $this->ticket();
        $image = $this->attachment($ticket, 'foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/ZxoAAAAASUVORK5CYII='));
        $pdf = $this->attachment($ticket, 'archivo.pdf', "%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF");
        foreach ([[$image, 'image', 'image/png'], [$pdf, 'pdf', 'application/pdf']] as [$file, $type, $mime]) {
            $this->actingAs($ticket->requester)->getJson(route('tickets.attachments.preview', [$ticket, $file]))
                ->assertOk()->assertJsonPath('type', $type)->assertJsonPath('url', route('tickets.attachments.inline', [$ticket, $file]));
            $response = $this->get(route('tickets.attachments.inline', [$ticket, $file]));
            $response->assertOk()->assertHeader('Content-Type', $mime)->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringNotContainsString('attachment;', $response->headers->get('Content-Disposition', ''));
        }
        $this->get(route('tickets.show', $ticket))->assertHeader('X-Frame-Options', 'DENY');
        $this->get(route('tickets.attachments.download', [$ticket, $image]))->assertOk()->assertDownload('foto.png');
    }

    public function test_xlsx_reads_shared_strings_sparse_cells_and_cached_formulas_without_executing_them(): void
    {
        $ticket = $this->ticket();
        $file = $this->attachment($ticket, 'gastos.xlsx', $this->office([
            'xl/sharedStrings.xml' => '<sst><si><t>Concepto</t></si><si><t>&lt;img src=x onerror=alert(1)&gt;</t></si></sst>',
            'xl/worksheets/sheet1.xml' => '<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="inlineStr"><is><t>Total</t></is></c></row><row r="2"><c r="A2" t="s"><v>1</v></c><c r="C2"><f>1+2</f><v>3</v></c></row></sheetData></worksheet>',
        ]));
        $this->actingAs($ticket->requester)->getJson(route('tickets.attachments.preview', [$ticket, $file]))
            ->assertOk()->assertJsonPath('type', 'spreadsheet')->assertJsonPath('sheets.0.rows.0.0', 'Concepto')
            ->assertJsonPath('sheets.0.rows.0.1', '')->assertJsonPath('sheets.0.rows.1.2', '3')
            ->assertJsonPath('sheets.0.rows.1.0', '<img src=x onerror=alert(1)>');
        $this->get(route('tickets.attachments.inline', [$ticket, $file]))->assertStatus(415);
    }

    public function test_office_preview_rejects_entities_corrupt_archives_and_large_decompressed_entries(): void
    {
        $ticket = $this->ticket();
        foreach ([
            'entidades.xlsx' => $this->office(['xl/worksheets/sheet1.xml' => '<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><worksheet>&x;</worksheet>']),
            'comprimido.xlsx' => $this->office(['xl/worksheets/sheet1.xml' => str_repeat(' ', 4 * 1024 * 1024 + 1)]),
            'corrupto.xlsx' => 'No es un archivo ZIP',
        ] as $name => $content) {
            $file = $this->attachment($ticket, $name, $content);
            $this->actingAs($ticket->requester)->getJson(route('tickets.attachments.preview', [$ticket, $file]))
                ->assertOk()->assertJsonPath('type', 'unsupported');
        }
    }

    public function test_docx_text_and_unsupported_files_have_safe_non_external_previews(): void
    {
        $ticket = $this->ticket();
        $doc = $this->attachment($ticket, 'informe.docx', $this->office([
            'word/document.xml' => '<document><body><p><r><t>Informe de prueba</t></r></p></body></document>',
        ]));
        $this->actingAs($ticket->requester)->getJson(route('tickets.attachments.preview', [$ticket, $doc]))
            ->assertOk()->assertJsonPath('type', 'text')->assertJsonPath('text', 'Informe de prueba');
        $zip = $this->attachment($ticket, 'archivo.zip', $this->office(['nota.txt' => 'Contenido privado']));
        $this->getJson(route('tickets.attachments.preview', [$ticket, $zip]))->assertOk()->assertJsonPath('type', 'unsupported');
    }

    public function test_star_rating_keeps_acceptance_validation_and_allows_rejection_without_rating(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['ticket_status_id' => TicketStatus::where('slug', 'solucionado')->value('id'), 'solution' => 'Se verificó y corrigió la conexión de red del equipo.']);
        foreach ([null, 0, 6, 2.5] as $rating) {
            $this->actingAs($ticket->requester)->postJson(route('tickets.review.store', $ticket), [
                'decision' => 'accepted', 'rating' => $rating, 'confirmation' => 1,
            ])->assertUnprocessable()->assertJsonValidationErrors('rating');
        }
        $this->postJson(route('tickets.review.store', $ticket), ['decision' => 'accepted', 'rating' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->post(route('tickets.review.store', $ticket), [
            'decision' => 'rejected', 'comment' => 'La falla continúa después de revisar la conexión.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('en-proceso', $ticket->fresh()->status->slug);
    }

    private function staff(): User
    {
        return User::where('email', 'soporte@zolicguate.com')->firstOrFail();
    }

    private function ticket(): Ticket
    {
        return Ticket::with('requester')->where('current_assignee_id', $this->staff()->id)
            ->where('is_draft', false)->whereNotIn('approval_status', ['pending', 'rejected', 'changes_requested'])
            ->whereHas('status', fn ($query) => $query->whereIn('slug', ['asignado', 'en-analisis', 'en-proceso']))->firstOrFail();
    }

    private function attachment(Ticket $ticket, string $name, string $content): TicketAttachment
    {
        $path = 'tickets/'.$ticket->id.'/'.bin2hex(random_bytes(6)).'/'.$name;
        Storage::disk('local')->put($path, $content);

        return $ticket->attachments()->create(['uploaded_by_id' => $this->staff()->id, 'context' => 'initial',
            'disk' => 'local', 'path' => $path, 'original_name' => $name, 'mime_type' => 'application/octet-stream',
            'size' => strlen($content), 'sha256' => hash('sha256', $content), 'is_internal' => false]);
    }

    private function office(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ticket-preview-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return $content;
    }
}
