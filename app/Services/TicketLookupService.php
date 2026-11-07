<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Supply;
use App\Models\Ticket;
use App\Models\User;

class TicketLookupService
{
    public function supplies(string $query, Ticket $ticket): array
    {
        $supplies = Supply::where('is_active', true)
            ->whereRaw("stock_quantity - COALESCE((SELECT SUM(quantity_reserved) FROM ticket_supply_usages WHERE supply_id = supplies.id AND status = 'reserved' AND ticket_id <> ?), 0) >= 1", [$ticket->id])
            ->whereDoesntHave('ticketUsages', fn ($builder) => $builder->where('ticket_id', $ticket->id)->where('status', 'consumed'))
            ->withSum(['ticketUsages as reserved_quantity' => fn ($builder) => $builder->where('status', 'reserved')->where('ticket_id', '!=', $ticket->id)], 'quantity_reserved')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($match) => $match
                ->where('code', 'like', '%'.$query.'%')->orWhere('name', 'like', '%'.$query.'%')))
            ->orderBy('code')->orderBy('id')->limit(21)->get();

        return ['results' => $supplies->take(20)->map(fn ($supply) => $this->supplyOption($supply, $ticket))->values(), 'has_more' => $supplies->count() > 20];
    }

    public function supplyOption(Supply $supply, Ticket $ticket): array
    {
        $reserved = array_key_exists('reserved_quantity', $supply->getAttributes())
            ? $supply->reserved_quantity
            : $supply->ticketUsages()->where('status', 'reserved')->where('ticket_id', '!=', $ticket->id)->sum('quantity_reserved');
        $available = (int) floor(max(0, (float) $supply->stock_quantity - (float) $reserved));

        return ['value' => (string) $supply->id, 'label' => $supply->code.' · '.$supply->name,
            'description' => $available.' disponibles · '.$supply->unit_label, 'available' => $available];
    }

    public function requesters(string $query): array
    {
        $users = User::where('status', 'active')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($match) => $match
                ->where('name', 'like', '%'.$query.'%')->orWhere('email', 'like', '%'.$query.'%')
                ->orWhere('employee_code', 'like', '%'.$query.'%')))
            ->orderBy('name')->orderBy('id')->limit(21)
            ->get(['id', 'name', 'email', 'employee_code', 'area_id', 'department_id']);

        return ['results' => $users->take(20)->map(fn ($user) => $this->requesterOption($user))->values(), 'has_more' => $users->count() > 20];
    }

    public function equipment(string $query): array
    {
        // Catálogo mínimo para vincular activos al ticket; no expone red, costos ni licencias.
        $assets = Equipment::where('is_active', true)->where('status', '!=', 'retired')
            ->when($query !== '', fn ($builder) => $builder->where(fn ($match) => $match
                ->where('inventory_code', 'like', '%'.$query.'%')->orWhere('name', 'like', '%'.$query.'%')))
            ->orderBy('inventory_code')->orderBy('id')->limit(21)
            ->get(['id', 'inventory_code', 'name', 'asset_type']);

        return ['results' => $assets->take(20)->map(fn ($asset) => $this->equipmentOption($asset))->values(), 'has_more' => $assets->count() > 20];
    }

    public function requesterOption(User $user): array
    {
        return [
            'value' => (string) $user->id, 'label' => $user->name,
            'description' => $user->email.($user->employee_code ? ' · '.$user->employee_code : ''),
            'area_id' => $user->area_id, 'department_id' => $user->department_id,
        ];
    }

    public function equipmentOption(Equipment $asset): array
    {
        return ['value' => (string) $asset->id, 'label' => $asset->inventory_code.' · '.$asset->name, 'description' => $asset->type_label];
    }
}
