<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketCategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('configuration.categories.index', [
            'categories' => TicketCategory::with('subcategories')
                ->withCount(['forms', 'approvalWorkflows', 'routingRules'])
                ->orderBy('sort_order')->orderBy('name')->get(),
            'editingCategory' => $request->integer('edit_category')
                ? TicketCategory::findOrFail($request->integer('edit_category')) : null,
            'editingSubcategory' => $request->integer('edit_subcategory')
                ? TicketSubcategory::with('category')->findOrFail($request->integer('edit_subcategory')) : null,
            'categoryTypes' => [
                'request' => 'Solicitud', 'incident' => 'Incidente', 'development' => 'Desarrollo',
            ],
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);
        $category = TicketCategory::create($this->categoryPayload($request, $validated));
        $this->audit($request, 'category_created', $category->id, $category->name);

        return redirect()->route('configuration.categories.index')->with('success', 'Categoría registrada correctamente.');
    }

    public function updateCategory(Request $request, TicketCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);
        $category->update($this->categoryPayload($request, $validated));
        $this->audit($request, 'category_updated', $category->id, $category->name);

        return redirect()->route('configuration.categories.index')->with('success', 'Categoría actualizada.');
    }

    public function storeSubcategory(Request $request): RedirectResponse
    {
        $validated = $this->validateSubcategory($request);
        $subcategory = TicketSubcategory::create($this->subcategoryPayload($request, $validated));
        $this->audit($request, 'subcategory_created', $subcategory->id, $subcategory->name);

        return redirect()->route('configuration.categories.index')->with('success', 'Subcategoría registrada correctamente.');
    }

    public function updateSubcategory(Request $request, TicketSubcategory $subcategory): RedirectResponse
    {
        $validated = $this->validateSubcategory($request, $subcategory);
        $subcategory->update($this->subcategoryPayload($request, $validated));
        $this->audit($request, 'subcategory_updated', $subcategory->id, $subcategory->name);

        return redirect()->route('configuration.categories.index')->with('success', 'Subcategoría actualizada.');
    }

    private function validateCategory(Request $request, ?TicketCategory $category = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('ticket_categories')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:160', Rule::unique('ticket_categories')->ignore($category?->id)],
            'type' => ['required', Rule::in(['request', 'incident', 'development'])],
            'description' => ['nullable', 'string', 'max:1500'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateSubcategory(Request $request, ?TicketSubcategory $subcategory = null): array
    {
        return $request->validate([
            'ticket_category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('ticket_subcategories')->where(
                    fn ($query) => $query->where('ticket_category_id', $request->integer('ticket_category_id')),
                )->ignore($subcategory?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1500'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function categoryPayload(Request $request, array $validated): array
    {
        return [
            'code' => Str::upper(trim($validated['code'])), 'name' => trim($validated['name']),
            'type' => $validated['type'], 'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'], 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function subcategoryPayload(Request $request, array $validated): array
    {
        return [
            'ticket_category_id' => $validated['ticket_category_id'], 'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']), 'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'], 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function audit(Request $request, string $event, int $recordId, string $recordName): void
    {
        SecurityEvent::record($event, $request, $request->user(), [
            'record_id' => $recordId, 'record_name' => $recordName, 'performed_by' => $request->user()->id,
        ]);
    }
}
