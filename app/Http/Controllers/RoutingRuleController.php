<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\RoutingRule;
use App\Models\SecurityEvent;
use App\Models\TechnicalGroup;
use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoutingRuleController extends Controller
{
    public function index(Request $request): View
    {
        return view('configuration.routing.index', [
            'rules' => RoutingRule::with(['category', 'subcategory', 'itArea', 'group', 'assignee'])
                ->orderByDesc('is_active')->orderBy('precedence')->orderBy('name')->get(),
            'categories' => TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'itAreas' => Area::where('type', 'it')->where('is_active', true)->orderBy('name')->get(),
            'groups' => TechnicalGroup::with('area')->where('is_active', true)->orderBy('name')->get(),
            'technicians' => User::with(['roles', 'technicalGroups'])->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['tecnico-it', 'programador-it', 'encargado-area-it', 'jefatura-it']))
                ->orderBy('name')->get(),
            'editingRule' => $request->integer('edit_rule') ? RoutingRule::findOrFail($request->integer('edit_rule')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRule($request);
        $rule = RoutingRule::create($this->payload($request, $validated));
        $this->audit($request, 'routing_rule_created', $rule);

        return redirect()->route('configuration.routing.index')->with('success', 'Regla de direccionamiento registrada.');
    }

    public function update(Request $request, RoutingRule $routingRule): RedirectResponse
    {
        $validated = $this->validateRule($request, $routingRule);
        $routingRule->update($this->payload($request, $validated));
        $this->audit($request, 'routing_rule_updated', $routingRule);

        return redirect()->route('configuration.routing.index')->with('success', 'Regla de direccionamiento actualizada.');
    }

    private function validateRule(Request $request, ?RoutingRule $rule = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'ticket_subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'it_area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where(fn ($query) => $query->where('type', 'it')->where('is_active', true))],
            'it_group_id' => ['nullable', 'integer', Rule::exists('it_groups', 'id')->where('is_active', true)],
            'assignee_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'precedence' => ['required', 'integer', 'between:1,999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (empty($validated['it_area_id']) && empty($validated['it_group_id']) && empty($validated['assignee_user_id'])) {
            throw ValidationException::withMessages(['it_area_id' => 'Selecciona al menos un área de IT, grupo técnico o responsable.']);
        }
        if (! empty($validated['ticket_subcategory_id']) && ! TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $validated['ticket_category_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_subcategory_id' => 'La subcategoría no pertenece a la categoría seleccionada.']);
        }

        if (! empty($validated['it_area_id']) && ! empty($validated['it_group_id'])) {
            $group = TechnicalGroup::with('area')->findOrFail($validated['it_group_id']);
            $areaIsCompatible = $group->area_id === (int) $validated['it_area_id']
                || $group->area?->parent_id === (int) $validated['it_area_id'];
            if (! $areaIsCompatible) {
                throw ValidationException::withMessages(['it_group_id' => 'El grupo técnico no pertenece al área de IT seleccionada.']);
            }
        }

        if (! empty($validated['it_group_id']) && ! empty($validated['assignee_user_id'])
            && ! TechnicalGroup::findOrFail($validated['it_group_id'])->members()->whereKey($validated['assignee_user_id'])->exists()) {
            throw ValidationException::withMessages(['assignee_user_id' => 'El responsable debe pertenecer al grupo técnico seleccionado.']);
        }

        $duplicate = RoutingRule::where('ticket_category_id', $validated['ticket_category_id'])
            ->when(
                $validated['ticket_subcategory_id'] ?? null,
                fn ($query, $subcategoryId) => $query->where('ticket_subcategory_id', $subcategoryId),
                fn ($query) => $query->whereNull('ticket_subcategory_id'),
            )
            ->where('precedence', $validated['precedence'])
            ->when($rule, fn ($query) => $query->whereKeyNot($rule->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['precedence' => 'Ya existe una regla con esa precedencia para la misma clasificación.']);
        }

        return $validated;
    }

    private function payload(Request $request, array $validated): array
    {
        return [
            'name' => trim($validated['name']), 'ticket_category_id' => $validated['ticket_category_id'],
            'ticket_subcategory_id' => $validated['ticket_subcategory_id'] ?? null,
            'it_area_id' => $validated['it_area_id'] ?? null, 'it_group_id' => $validated['it_group_id'] ?? null,
            'assignee_user_id' => $validated['assignee_user_id'] ?? null,
            'precedence' => $validated['precedence'], 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function audit(Request $request, string $event, RoutingRule $rule): void
    {
        SecurityEvent::record($event, $request, $request->user(), [
            'record_id' => $rule->id, 'record_name' => $rule->name, 'performed_by' => $request->user()->id,
        ]);
    }
}
