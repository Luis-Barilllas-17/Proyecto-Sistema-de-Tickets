<?php

namespace App\Services;

use App\Models\TicketAttachment;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** Read-only, bounded previews. Never extract archives or execute Office formulas. */
class TicketAttachmentPreviewService
{
    public function inlineType(TicketAttachment $attachment): ?string
    {
        $path = Storage::disk($attachment->disk)->path($attachment->path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true) ? $mime : null;
    }

    public function preview(TicketAttachment $attachment): array
    {
        $path = Storage::disk($attachment->disk)->path($attachment->path);
        $type = $this->inlineType($attachment);
        if ($type) {
            return ['type' => str_starts_with($type, 'image/') ? 'image' : 'pdf'];
        }

        $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
        if (in_array($extension, ['txt', 'csv'], true)) {
            $content = file_get_contents($path, false, null, 0, 100001);

            return ['type' => 'text', 'text' => mb_convert_encoding(substr($content, 0, 100000), 'UTF-8', 'UTF-8'),
                'notice' => strlen($content) > 100000 ? 'Vista limitada a los primeros 100 KB.' : 'Vista de texto; los datos no se ejecutan.'];
        }

        if (in_array($extension, ['xlsx', 'docx'], true) && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive;
            if ($zip->open($path) === true) {
                try {
                    return $extension === 'xlsx' ? $this->spreadsheet($zip) : $this->document($zip);
                } catch (RuntimeException) {
                    return ['type' => 'unsupported', 'notice' => 'No se pudo generar una vista segura de este documento. Puedes descargar el original.'];
                } finally {
                    $zip->close();
                }
            }
        }

        return ['type' => 'unsupported', 'notice' => 'Este formato no tiene vista previa en el navegador. Puedes descargar el archivo original.'];
    }

    private function xml(ZipArchive $zip, string $entry, int &$budget): DOMXPath
    {
        $stat = $zip->statName($entry);
        // Cap decompressed bytes before opening any entry (including shared strings).
        if (! $stat || $stat['size'] > 4 * 1024 * 1024 || ($budget -= $stat['size']) < 0) {
            throw new RuntimeException('Preview limit.');
        }
        $xml = $zip->getFromName($entry, 4 * 1024 * 1024);
        if ($xml === false || substr_count($xml, '<') > 50000 || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('Unsafe XML.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) || $document->doctype !== null) {
                throw new RuntimeException('Invalid XML.');
            }

            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function spreadsheet(ZipArchive $zip): array
    {
        $budget = 10 * 1024 * 1024;
        $strings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xpath = $this->xml($zip, 'xl/sharedStrings.xml', $budget);
            foreach ($xpath->query('//*[local-name()="si"]') as $index => $string) {
                if ($index >= 20000) {
                    throw new RuntimeException('Too many shared strings.');
                }
                $value = '';
                foreach ($xpath->query('.//*[local-name()="t"]', $string) as $text) {
                    $value .= $text->textContent;
                }
                $strings[] = mb_substr($value, 0, 2000);
            }
        }
        $sheets = [];
        // Fixed archive entry patterns only; no relationships, URLs or file extraction.
        for ($index = 0; $index < min($zip->numFiles, 1000) && count($sheets) < 3; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! preg_match('~^xl/worksheets/sheet[0-9]+\.xml$~', $entry)) {
                continue;
            }
            $xpath = $this->xml($zip, $entry, $budget);
            $rows = [];
            $rowNumbers = [];
            foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                if (count($rows) >= 100) {
                    break;
                }
                $values = [];
                foreach ($xpath->query('./*[local-name()="c"]', $row) as $cell) {
                    preg_match('/^([A-Z]{1,3})[0-9]+$/', $cell->getAttribute('r'), $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? '') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    if ($column < 1 || $column > 30) {
                        continue;
                    }
                    while (count($values) < $column) {
                        $values[] = '';
                    }
                    $value = $xpath->evaluate('string(./*[local-name()="v"])', $cell);
                    if ($cell->getAttribute('t') === 's') {
                        $value = $strings[(int) $value] ?? '';
                    } elseif ($cell->getAttribute('t') === 'inlineStr') {
                        $value = '';
                        foreach ($xpath->query('./*[local-name()="is"]//*[local-name()="t"]', $cell) as $text) {
                            $value .= $text->textContent;
                        }
                    } elseif ($value === '' && $xpath->query('./*[local-name()="f"]', $cell)->length) {
                        $value = '(Fórmula sin resultado guardado)';
                    }
                    $values[$column - 1] = mb_substr($value, 0, 2000);
                }
                $rows[] = $values;
                $rowNumbers[] = $row->getAttribute('r') ?: (string) count($rows);
            }
            $sheets[] = ['name' => 'Hoja '.(count($sheets) + 1), 'rows' => $rows, 'row_numbers' => $rowNumbers];
        }
        if (! $sheets) {
            throw new RuntimeException('No readable worksheets.');
        }

        return ['type' => 'spreadsheet', 'sheets' => $sheets,
            'notice' => 'Vista de datos: hasta 3 hojas, 100 filas y 30 columnas por hoja. Sin formato de Office; las fórmulas muestran su resultado guardado y no se ejecutan.'];
    }

    private function document(ZipArchive $zip): array
    {
        $budget = 4 * 1024 * 1024;
        $xpath = $this->xml($zip, 'word/document.xml', $budget);
        $paragraphs = [];
        $remaining = 100000;
        foreach ($xpath->query('//*[local-name()="body"]//*[local-name()="p"]') as $paragraph) {
            if (count($paragraphs) >= 300 || $remaining <= 0) {
                break;
            }
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t"]', $paragraph) as $part) {
                $text .= $part->textContent;
            }
            $text = mb_substr($text, 0, $remaining);
            $remaining -= mb_strlen($text);
            $paragraphs[] = $text;
        }

        return ['type' => 'text', 'text' => implode("\n\n", $paragraphs),
            'notice' => 'Vista de texto del documento, sin formato ni imágenes. Hasta 300 párrafos y 100 000 caracteres.'];
    }
}
