<?php

use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/ticket-environment.php';
$directory = ticketQaEnvironment();
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($directory.'/qa.sqlite')) {
    throw new RuntimeException('Solo se permite preparar la base SQLite aislada.');
}

$staff = User::where('email', 'qa.tickets.tecnico@zolicguate.com')->firstOrFail();
$requester = User::where('email', 'qa.tickets.usuario@zolicguate.com')->firstOrFail();
$base = Ticket::where('source', 'requester')->where('is_draft', false)->firstOrFail();
$ticket = $base->replicate();
$ticket->fill([
    'number' => 'QA-DET-'.date('ymdHis'), 'title' => 'QA Detalle: conexión de red intermitente',
    'description' => 'Caso aislado para validar búsqueda de insumos, cantidades enteras, adjuntos y calificación.',
    'requester_id' => $requester->id, 'created_by_id' => $staff->id,
    'requester_area_id' => $requester->area_id, 'requester_department_id' => $requester->department_id,
    'ticket_status_id' => TicketStatus::where('slug', 'en-proceso')->value('id'),
    'approval_status' => 'not_required', 'current_assignee_id' => $staff->id,
    'current_it_area_id' => $staff->area_id, 'current_it_group_id' => TechnicalGroup::where('code', 'GRP-SOP')->value('id'),
    'assignment_status' => 'unassigned', 'solution' => null, 'solution_by_id' => null, 'solved_at' => null,
    'closed_at' => null, 'closed_by_id' => null, 'cancelled_at' => null, 'cancellation_reason' => null,
    'lock_version' => 1, 'last_activity_at' => now(),
]);
if (in_array('--unassigned', $argv, true)) {
    $ticket->fill(['current_assignee_id' => null, 'current_it_area_id' => null, 'current_it_group_id' => null]);
} else {
    $ticket->assignment_status = 'accepted';
}
$ticket->save();

$zipPath = tempnam(sys_get_temp_dir(), 'ticket-qa-office-');
$zip = new ZipArchive;
$zip->open($zipPath, ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Gastos QA" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Concepto</t></is></c><c r="B1" t="inlineStr"><is><t>Cantidad</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Conectores de red</t></is></c><c r="B2"><v>2</v></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>&lt;img src=x onerror=alert(1)&gt;</t></is></c><c r="B3"><f>1+2</f><v>3</v></c></row></sheetData></worksheet>');
$zip->close();
$xlsx = file_get_contents($zipPath);
unlink($zipPath);

// Small valid PDF; offsets generated from its own bytes.
$pdf = "%PDF-1.4\n";
$offsets = [0];
$stream = 'BT /F1 18 Tf 30 160 Td (Evidencia de prueba QA) Tj ET';
$objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream"];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 6\n0000000000 65535 f \n";
foreach (array_slice($offsets, 1) as $offset) {
    $pdf .= sprintf("%010d 00000 n \n", $offset);
}
$pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

$files = [
    ['gastos-prueba.xlsx', $xlsx, false], ['evidencia-prueba.pdf', $pdf, false],
    ['registro-prueba.txt', "Evidencia de red\nLectura privada sin descarga obligatoria.\n<script>alert('prueba')</script>", false],
    ['imagen-prueba.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/ZxoAAAAASUVORK5CYII='), false],
    ['nota-interna-prueba.txt', 'Solo visible para el personal autorizado de IT.', true],
    ['archivo-no-compatible.zip', $xlsx, false],
];
$attachments = [];
foreach ($files as [$name, $content, $internal]) {
    $path = 'tickets/'.$ticket->id.'/qa-detail/'.$name;
    Storage::disk('local')->put($path, $content);
    $file = $ticket->attachments()->create(['uploaded_by_id' => $staff->id, 'context' => $internal ? 'internal_note' : 'initial',
        'disk' => 'local', 'path' => $path, 'original_name' => $name, 'mime_type' => (new finfo(FILEINFO_MIME_TYPE))->buffer($content),
        'size' => strlen($content), 'sha256' => hash('sha256', $content), 'is_internal' => $internal]);
    $attachments[$name] = $file->id;
}
$manifest = ['ticket' => $ticket->id, 'number' => $ticket->number, 'attachments' => $attachments];
file_put_contents($directory.'/detail-fixture.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
