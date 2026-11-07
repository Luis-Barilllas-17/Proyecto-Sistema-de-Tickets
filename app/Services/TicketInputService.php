<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\FormField;
use App\Models\Ticket;
use App\Models\TicketForm;
use App\Models\TicketSubcategory;
use App\Models\User;
use App\Support\UploadSecurityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketInputService
{
    public function validate(Request $request, bool $submitting, ?Ticket $ticket = null, bool $staffForm = false): array
    {
        $required = $submitting ? 'required' : 'nullable';
        $validated = $request->validate([
            'ticket_category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'ticket_subcategory_id' => [$required, 'integer', Rule::exists('ticket_subcategories', 'id')->where('is_active', true)],
            'title' => [$required, 'string', 'max:200'],
            'description' => [$required, 'string', 'max:15000'],
            'requester_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'requester_area_id' => [$required, 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'requester_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'location' => [$required, 'string', 'max:255'],
            'attention_type' => [$required, Rule::in(array_keys(Ticket::ATTENTION_TYPES))],
            'impact_level_id' => [$required, 'integer', Rule::exists('impact_levels', 'id')->where('is_active', true)],
            'urgency_level_id' => [$required, 'integer', Rule::exists('urgency_levels', 'id')->where('is_active', true)],
            'is_internal' => ['nullable', 'boolean'],
            'fields' => ['nullable', 'array', 'max:100'],
            'equipment_codes' => ['prohibited'],
            'equipment_ids' => ['nullable', 'array', 'max:10'],
            'equipment_ids.*' => ['required', 'integer', 'distinct', 'exists:equipment,id'],
            'legacy_equipment_link_ids' => ['nullable', 'array', 'max:10'],
            'legacy_equipment_link_ids.*' => ['required', 'integer', 'distinct', Rule::exists('ticket_equipment_links', 'id')
                ->where('ticket_id', $ticket?->id ?? 0)->whereNull('equipment_id')],
            'attachments' => ['nullable', 'array', 'max:'.UploadSecurityPolicy::MAX_FILES_PER_REQUEST],
            'attachments.*' => UploadSecurityPolicy::fileRules(TicketAttachmentService::ALLOWED_EXTENSIONS),
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ], [
            'ticket_category_id.required' => 'Selecciona una categoría.',
            'ticket_subcategory_id.required' => 'Selecciona una subcategoría.',
            'title.required' => 'Ingresa el asunto del ticket.',
            'description.required' => 'Describe el problema o trabajo que requiere atención.',
            'requester_area_id.required' => 'Selecciona el área solicitante.',
            'location.required' => 'Indica la ubicación de la atención.',
            'attention_type.required' => 'Selecciona el tipo de atención.',
            'impact_level_id.required' => 'Selecciona el nivel de impacto.',
            'urgency_level_id.required' => 'Selecciona el nivel de urgencia.',
            'attachments.*' => 'Uno de los archivos no es válido, supera 5 MB o tiene un tipo no permitido.',
            'equipment_codes.prohibited' => 'Selecciona los equipos registrados en el inventario; no ingreses códigos manualmente.',
            'equipment_ids.max' => 'Puedes vincular un máximo de 10 equipos.',
            'equipment_ids.*.exists' => 'Uno de los equipos seleccionados no está registrado en el inventario.',
            'equipment_ids.*.distinct' => 'No selecciones el mismo equipo más de una vez.',
            'legacy_equipment_link_ids.*.exists' => 'La referencia anterior no pertenece a este ticket.',
        ]);

        if (! empty($validated['ticket_subcategory_id']) && ! TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $validated['ticket_category_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_subcategory_id' => 'La subcategoría no pertenece a la categoría seleccionada.']);
        }
        if (! empty($validated['requester_department_id']) && ! Department::whereKey($validated['requester_department_id'])
            ->where('area_id', $validated['requester_area_id'])->exists()) {
            throw ValidationException::withMessages(['requester_department_id' => 'El departamento no pertenece al área solicitante.']);
        }

        $form = $staffForm ? null : $this->matchingForm($validated['ticket_category_id'], $validated['ticket_subcategory_id'] ?? null);
        $validated['ticket_form_id'] = $staffForm ? $ticket?->ticket_form_id : $form?->id;
        $validated['dynamic_values'] = $staffForm
            ? ($ticket?->fieldValues->map(fn ($value) => [
                'field_id' => $value->form_field_id, 'value' => $value->value, 'value_json' => $value->value_json,
            ])->all() ?? [])
            : $this->validateDynamicFields($request, $form, $submitting, $ticket);
        $validated['equipment'] = $this->selectedEquipment($validated, $ticket);

        return $validated;
    }

    public function ticketPayload(array $validated, User $requester, User $creator, bool $internal): array
    {
        return [
            'requester_id' => $requester->id, 'created_by_id' => $creator->id,
            'source' => $internal ? 'it_internal' : 'requester',
            'title' => $this->cleanText($validated['title'] ?? null),
            'description' => $this->cleanText($validated['description'] ?? null),
            'ticket_category_id' => $validated['ticket_category_id'],
            'ticket_subcategory_id' => $validated['ticket_subcategory_id'] ?? null,
            'ticket_form_id' => $validated['ticket_form_id'],
            'requester_area_id' => $validated['requester_area_id'] ?? null,
            'requester_department_id' => $validated['requester_department_id'] ?? null,
            'location' => $this->cleanText($validated['location'] ?? null),
            'attention_type' => $validated['attention_type'] ?? null,
            'impact_level_id' => $validated['impact_level_id'] ?? null,
            'urgency_level_id' => $validated['urgency_level_id'] ?? null,
        ];
    }

    public function syncValues(Ticket $ticket, array $values): void
    {
        $validFieldIds = collect($values)->pluck('field_id');
        if ($validFieldIds->isEmpty()) {
            $ticket->fieldValues()->delete();
        } else {
            $ticket->fieldValues()->whereNotIn('form_field_id', $validFieldIds->all())->delete();
        }
        foreach ($values as $value) {
            $ticket->fieldValues()->updateOrCreate(['form_field_id' => $value['field_id']], [
                'value' => $value['value'], 'value_json' => $value['value_json'],
            ]);
        }
    }

    public function syncEquipment(Ticket $ticket, array $equipment, User $actor): void
    {
        $kept = [];
        foreach ($equipment as $item) {
            if (isset($item['legacy_link_id'])) {
                $kept[] = $item['legacy_link_id'];

                continue;
            }
            $link = $ticket->equipmentLinks()->firstOrNew(['equipment_id' => $item['id']]);
            $link->fill(['inventory_code' => $item['code'], 'equipment_name' => $item['name']]);
            if (! $link->exists) {
                $link->linked_by_id = $actor->id;
            }
            $link->save();
            $kept[] = $link->id;
        }
        $ticket->equipmentLinks()->whereNotIn('id', $kept)->delete();
    }

    public function matchingForm(int $categoryId, ?int $subcategoryId): ?TicketForm
    {
        return TicketForm::with(['fields' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)->where('ticket_category_id', $categoryId)
            ->where(fn ($query) => $query->whereNull('ticket_subcategory_id')->when($subcategoryId, fn ($inner) => $inner->orWhere('ticket_subcategory_id', $subcategoryId)))
            ->get()->sortByDesc(fn ($form) => $form->ticket_subcategory_id ? 1 : 0)->first();
    }

    public function baseFieldName(FormField $field): ?string
    {
        if ($field->is_readonly) {
            return null;
        }

        return match (Str::lower($field->name)) {
            'asunto', 'title' => $field->type === 'text' ? 'title' : null,
            'descripcion', 'description' => $field->type === 'textarea' ? 'description' : null,
            default => null,
        };
    }

    private function validateDynamicFields(Request $request, ?TicketForm $form, bool $submitting, ?Ticket $ticket): array
    {
        if (! $form) {
            return [];
        }

        $provided = $request->input('fields', []);
        $values = [];
        $errors = [];
        foreach ($form->fields as $field) {
            if ($baseField = $this->baseFieldName($field)) {
                $value = $this->cleanText($request->input($baseField));
                if ($value !== null && $value !== '') {
                    // Conserva compatibilidad con las respuestas de formularios ya configurados.
                    $values[] = ['field_id' => $field->id, 'value' => $value, 'value_json' => null];
                }

                continue;
            }
            $raw = $field->is_readonly ? $field->default_value : ($provided[$field->id] ?? null);
            if ($field->type === 'checkbox') {
                $raw = filter_var($raw, FILTER_VALIDATE_BOOL) ? '1' : '0';
                if ($submitting && $field->is_required && $field->is_visible && $raw === '0') {
                    $errors["fields.$field->id"] = 'Debes marcar «'.$field->label.'» para continuar.';

                    continue;
                }
            }
            if ($field->type === 'file') {
                if ($submitting && $field->is_required && $field->is_visible && ! $request->hasFile('attachments')
                    && ! $ticket?->attachments()->where('is_internal', false)->exists()) {
                    $errors["fields.$field->id"] = 'Adjunta al menos un archivo para «'.$field->label.'».';
                }

                continue;
            }

            $empty = $raw === null || (is_string($raw) && trim($raw) === '');
            if ($submitting && $field->is_required && $field->is_visible && $empty) {
                $errors["fields.$field->id"] = 'El campo «'.$field->label.'» es obligatorio.';

                continue;
            }
            if ($empty) {
                continue;
            }

            $normalized = $this->validateFieldValue($field, $raw, $errors);
            if ($normalized !== null) {
                $values[] = ['field_id' => $field->id, 'value' => $normalized, 'value_json' => null];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    private function validateFieldValue(FormField $field, mixed $raw, array &$errors): ?string
    {
        $key = "fields.$field->id";
        if (is_array($raw)) {
            $errors[$key] = 'El formato de «'.$field->label.'» no es válido.';

            return null;
        }
        $value = $this->cleanText((string) $raw);

        if (in_array($field->type, ['text', 'select', 'radio', 'date'], true) && mb_strlen($value) > 255) {
            $errors[$key] = 'El campo «'.$field->label.'» supera el tamaño permitido.';
        } elseif ($field->type === 'textarea' && mb_strlen($value) > 5000) {
            $errors[$key] = 'El campo «'.$field->label.'» supera 5,000 caracteres.';
        } elseif ($field->type === 'number' && ! is_numeric($value)) {
            $errors[$key] = 'El campo «'.$field->label.'» debe ser numérico.';
        } elseif ($field->type === 'number' && $field->min_value !== null && (float) $value < $field->min_value) {
            $errors[$key] = 'El campo «'.$field->label.'» debe ser al menos '.$field->min_value.'.';
        } elseif ($field->type === 'number' && $field->max_value !== null && (float) $value > $field->max_value) {
            $errors[$key] = 'El campo «'.$field->label.'» no puede superar '.$field->max_value.'.';
        } elseif ($field->type === 'date' && ! $this->validDate($value)) {
            $errors[$key] = 'El campo «'.$field->label.'» debe contener una fecha válida.';
        } elseif (in_array($field->type, ['select', 'radio'], true) && ! in_array($value, $field->options ?? [], true)) {
            $errors[$key] = 'Selecciona una opción válida para «'.$field->label.'».';
        } elseif ($field->type === 'checkbox' && ! in_array($value, ['0', '1'], true)) {
            $errors[$key] = 'El campo «'.$field->label.'» no es válido.';
        }

        return isset($errors[$key]) ? null : $value;
    }

    private function selectedEquipment(array $validated, ?Ticket $ticket): array
    {
        $ids = $validated['equipment_ids'] ?? [];
        $legacyIds = $validated['legacy_equipment_link_ids'] ?? [];
        if (count($ids) + count($legacyIds) > 10) {
            throw ValidationException::withMessages(['equipment_ids' => 'Puedes vincular un máximo de 10 equipos, incluidas las referencias anteriores.']);
        }
        $existing = $ticket?->equipmentLinks()->whereNotNull('equipment_id')->pluck('equipment_id')->map(fn ($id) => (int) $id)->all() ?? [];
        $assets = Equipment::whereIn('id', $ids)->get()->keyBy('id');
        $result = [];
        foreach ($ids as $id) {
            $asset = $assets->get($id);
            if ((! $asset->is_active || $asset->status === 'retired') && ! in_array((int) $id, $existing, true)) {
                throw ValidationException::withMessages(['equipment_ids' => 'Solo puedes agregar equipos activos que no estén dados de baja.']);
            }
            $result[] = ['id' => $asset->id, 'code' => $asset->inventory_code, 'name' => $asset->name];
        }
        foreach ($legacyIds as $id) {
            $result[] = ['legacy_link_id' => (int) $id];
        }

        return $result;
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
