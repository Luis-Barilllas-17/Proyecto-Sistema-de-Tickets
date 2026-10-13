<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Supply;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Equipment::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(Equipment::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(Equipment::STATUSES))],
        ]);

        $query = Equipment::with(['area', 'department', 'responsible'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search): void {
                $inner->where('inventory_code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('serial_number', 'like', '%'.$search.'%')->orWhere('hostname', 'like', '%'.$search.'%');
            }))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('asset_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return view('inventory.index', [
            'equipment' => $query->orderBy('inventory_code')->paginate(15)->withQueryString(),
            'filters' => $filters,
            'metrics' => [
                'total' => Equipment::count(),
                'assigned' => Equipment::where('status', 'assigned')->count(),
                'attention' => Equipment::whereIn('status', ['maintenance', 'repair'])->count(),
                'lowStock' => Supply::whereColumn('stock_quantity', '<=', 'minimum_quantity')->where('is_active', true)->count(),
            ],
            'lowStockSupplies' => Supply::whereColumn('stock_quantity', '<=', 'minimum_quantity')->where('is_active', true)
                ->orderBy('stock_quantity')->limit(6)->get(),
        ]);
    }
}
