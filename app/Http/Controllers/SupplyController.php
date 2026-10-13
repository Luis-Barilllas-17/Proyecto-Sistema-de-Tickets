<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\TicketSupplyUsage;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplyController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supply::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'stock' => ['nullable', Rule::in(['all', 'low', 'available'])],
        ]);
        $query = Supply::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when(($filters['stock'] ?? null) === 'low', fn ($query) => $query->whereColumn('stock_quantity', '<=', 'minimum_quantity'))
            ->when(($filters['stock'] ?? null) === 'available', fn ($query) => $query->where('stock_quantity', '>', 0));

        return view('inventory.supplies.index', [
            'supplies' => $query->orderBy('name')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'categories' => Supply::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'metrics' => [
                'total' => Supply::where('is_active', true)->count(),
                'units' => Supply::where('is_active', true)->sum('stock_quantity'),
                'low' => Supply::where('is_active', true)->whereColumn('stock_quantity', '<=', 'minimum_quantity')->count(),
                'reserved' => TicketSupplyUsage::where('status', 'reserved')->sum('quantity_reserved'),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supply::class);

        return view('inventory.supplies.form', ['supply' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Supply::class);
        $validated = $this->validatedSupply($request);
        $supply = DB::transaction(function () use ($request, $validated): Supply {
            $initial = (float) ($validated['initial_quantity'] ?? 0);
            $supply = Supply::create($this->payload($validated) + [
                'stock_quantity' => 0,
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);
            if ($initial > 0) {
                $this->inventory->manualSupplyMovement($supply, 'entry', $initial, 'Existencia inicial registrada al crear el insumo.', $request->user(), $request->user()->id);
            }

            return $supply;
        });

        return redirect()->route('inventory.supplies.show', $supply)->with('success', 'Insumo registrado correctamente.');
    }

    public function show(Supply $supply): View
    {
        $this->authorize('view', $supply);
        $supply->load(['movements.ticket', 'movements.responsible', 'movements.authorizedBy', 'ticketUsages.ticket', 'ticketUsages.recordedBy']);

        return view('inventory.supplies.show', [
            'supply' => $supply,
            'authorizers' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function edit(Supply $supply): View
    {
        $this->authorize('update', $supply);

        return view('inventory.supplies.form', compact('supply'));
    }

    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $this->authorize('update', $supply);
        $validated = $this->validatedSupply($request, $supply);
        $supply->update($this->payload($validated) + ['updated_by_id' => $request->user()->id]);

        return redirect()->route('inventory.supplies.show', $supply)->with('success', 'Ficha del insumo actualizada.');
    }

    private function validatedSupply(Request $request, ?Supply $supply = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\/-]*$/', Rule::unique('supplies', 'code')->ignore($supply?->id)],
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'category' => ['required', 'string', 'min:2', 'max:100'],
            'unit' => ['required', Rule::in(array_keys(Supply::UNITS))],
            'initial_quantity' => [$supply ? 'nullable' : 'required', 'numeric', 'between:0,9999999999.99'],
            'minimum_quantity' => ['required', 'numeric', 'between:0,9999999999.99'],
            'storage_location' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function payload(array $validated): array
    {
        return [
            'code' => strtoupper($validated['code']),
            'name' => $this->inventory->clean($validated['name']),
            'category' => $this->inventory->clean($validated['category']),
            'unit' => $validated['unit'],
            'minimum_quantity' => $validated['minimum_quantity'],
            'storage_location' => $this->inventory->clean($validated['storage_location']),
            'description' => $this->inventory->clean($validated['description'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];
    }
}
