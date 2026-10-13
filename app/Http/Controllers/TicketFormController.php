<?php

namespace App\Http\Controllers;

use App\Models\FormField;
use App\Models\SecurityEvent;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketFormController extends Controller
{
    public function index(): View
    {
        return view('configuration.forms.index', [
            'forms' => TicketForm::with(['category', 'subcategory'])->withCount('fields')
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('configuration.forms.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        $form = DB::transaction(function () use ($request, $validated): TicketForm {
            $form = TicketForm::create($this->formPayload($request, $validated));
            $this->audit($request, 'form_created', $form->id, $form->name);

            return $form;
        });

        return redirect()->route('configuration.forms.edit', $form)
            ->with('success', 'Formulario creado. Ahora puedes agregar sus campos.');
    }

    public function edit(TicketForm $form): View
    {
        $form->load(['category', 'subcategory', 'fields']);

        return view('configuration.forms.form', $this->formData($form));
    }

    public function update(Request $request, TicketForm $form): RedirectResponse
    {
        $validated = $this->validateForm($request, $form);

        DB::transaction(function () use ($request, $validated, $form): void {
            $payload = $this->formPayload($request, $validated);
            $payload['version'] = min(65535, $form->version + 1);
            $form->update($payload);
            $this->audit($request, 'form_updated', $form->id, $form->name);
        });

        return redirect()->route('configuration.forms.edit', $form)->with('success', 'Formulario actualizado.');
    }

    public function storeField(Request $request, TicketForm $form): RedirectResponse
    {
        $validated = $this->validateField($request, $form);
        $field = $form->fields()->create($this->fieldPayload($request, $validated));
        $this->audit($request, 'form_field_created', $field->id, $field->label, ['form_id' => $form->id]);

        return redirect()->route('configuration.forms.edit', $form)->with('success', 'Campo agregado al formulario.');
    }

    public function updateField(Request $request, TicketForm $form, FormField $field): RedirectResponse
    {
        abort_unless($field->ticket_form_id === $form->id, 404);
        $validated = $this->validateField($request, $form, $field);
        $field->update($this->fieldPayload($request, $validated));
        $this->audit($request, 'form_field_updated', $field->id, $field->label, ['form_id' => $form->id]);

        return redirect()->route('configuration.forms.edit', $form)->with('success', 'Campo actualizado.');
    }

    private function validateForm(Request $request, ?TicketForm $form = null): array
    {
        $validated = $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'ticket_subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('ticket_forms')->ignore($form?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->ensureSubcategoryBelongsToCategory($validated);

        return $validated;
    }

    private function validateField(Request $request, TicketForm $form, ?FormField $field = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('form_fields')->where(fn ($query) => $query->where('ticket_form_id', $form->id))->ignore($field?->id),
            ],
            'label' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(FormField::TYPES))],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'default_value' => ['nullable', 'string', 'max:1000'],
            'options_text' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'min_value' => ['nullable', 'integer', 'between:0,1000000'],
            'max_value' => ['nullable', 'integer', 'between:0,1000000'],
            'is_required' => ['nullable', 'boolean'],
            'is_visible' => ['nullable', 'boolean'],
            'is_readonly' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $options = $this->parseOptions($validated['options_text'] ?? null);
        if (in_array($validated['type'], ['select', 'radio'], true) && count($options) < 2) {
            throw ValidationException::withMessages(['options_text' => 'Agrega al menos dos opciones diferentes.']);
        }
        if (($validated['min_value'] ?? null) !== null && ($validated['max_value'] ?? null) !== null
            && $validated['min_value'] > $validated['max_value']) {
            throw ValidationException::withMessages(['max_value' => 'El valor máximo debe ser mayor o igual al mínimo.']);
        }

        $validated['options'] = $options ?: null;

        return $validated;
    }

    private function formPayload(Request $request, array $validated): array
    {
        return [
            'ticket_category_id' => $validated['ticket_category_id'],
            'ticket_subcategory_id' => $validated['ticket_subcategory_id'] ?? null,
            'code' => Str::upper(trim($validated['code'])), 'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null, 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function fieldPayload(Request $request, array $validated): array
    {
        return [
            'name' => $validated['name'], 'label' => trim($validated['label']), 'type' => $validated['type'],
            'placeholder' => $validated['placeholder'] ?? null, 'help_text' => $validated['help_text'] ?? null,
            'default_value' => $validated['default_value'] ?? null, 'options' => $validated['options'],
            'sort_order' => $validated['sort_order'], 'min_value' => $validated['min_value'] ?? null,
            'max_value' => $validated['max_value'] ?? null, 'is_required' => $request->boolean('is_required'),
            'is_visible' => $request->boolean('is_visible'), 'is_readonly' => $request->boolean('is_readonly'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function ensureSubcategoryBelongsToCategory(array $validated): void
    {
        if (! empty($validated['ticket_subcategory_id']) && ! TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $validated['ticket_category_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_subcategory_id' => 'La subcategoría no pertenece a la categoría seleccionada.']);
        }
    }

    private function parseOptions(?string $input): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $input))
            ->map(fn ($option) => trim(strip_tags($option)))->filter()->unique()->take(20)
            ->map(fn ($option) => mb_substr($option, 0, 100))->values()->all();
    }

    private function formData(?TicketForm $form = null): array
    {
        return [
            'form' => $form,
            'categories' => TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'fieldTypes' => FormField::TYPES,
        ];
    }

    private function audit(Request $request, string $event, int $recordId, string $recordName, array $extra = []): void
    {
        SecurityEvent::record($event, $request, $request->user(), array_merge([
            'record_id' => $recordId, 'record_name' => $recordName, 'performed_by' => $request->user()->id,
        ], $extra));
    }
}
