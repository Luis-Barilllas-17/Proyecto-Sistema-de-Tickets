<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Supply;
use App\Models\SupplyMovement;
use App\Models\Ticket;
use App\Models\TicketSupplyUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function moveEquipment(Equipment $equipment, array $data, User $actor): void
    {
        DB::transaction(function () use ($equipment, $data, $actor): void {
            $equipment = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
            $before = [
                'area_id' => $equipment->area_id,
                'department_id' => $equipment->department_id,
                'responsible_user_id' => $equipment->responsible_user_id,
                'location' => $equipment->location,
                'status' => $equipment->status,
            ];

            $type = $data['movement_type'];
            $toResponsible = $type === 'return' || $type === 'retirement' ? null : ($data['to_responsible_id'] ?? null);
            $toArea = $type === 'return' || $type === 'retirement' ? ($data['to_area_id'] ?? null) : ($data['to_area_id'] ?? $equipment->area_id);
            $toDepartment = $type === 'return' || $type === 'retirement' ? ($data['to_department_id'] ?? null) : ($data['to_department_id'] ?? $equipment->department_id);
            $toStatus = match ($type) {
                'assignment' => 'assigned',
                'return' => $data['to_status'] ?? 'available',
                'retirement' => 'retired',
                default => $data['to_status'],
            };
            $toLocation = $this->clean($data['to_location'] ?? $equipment->location);

            if ($type === 'assignment' && ! $toResponsible) {
                throw ValidationException::withMessages(['to_responsible_id' => 'Selecciona al usuario responsable del equipo.']);
            }
            if (! $toLocation) {
                throw ValidationException::withMessages(['to_location' => 'Registra la ubicación de destino.']);
            }

            $equipment->movements()->create([
                'movement_type' => $type,
                'from_area_id' => $before['area_id'],
                'to_area_id' => $toArea,
                'from_department_id' => $before['department_id'],
                'to_department_id' => $toDepartment,
                'from_responsible_id' => $before['responsible_user_id'],
                'to_responsible_id' => $toResponsible,
                'from_location' => $before['location'],
                'to_location' => $toLocation,
                'from_status' => $before['status'],
                'to_status' => $toStatus,
                'effective_at' => $data['effective_at'],
                'justification' => $this->clean($data['justification']),
                'authorized_by_id' => $data['authorized_by_id'],
                'created_by_id' => $actor->id,
            ]);
            $equipment->update([
                'area_id' => $toArea,
                'department_id' => $toDepartment,
                'responsible_user_id' => $toResponsible,
                'location' => $toLocation,
                'status' => $toStatus,
                'is_active' => $type !== 'retirement',
                'updated_by_id' => $actor->id,
            ]);
        });
    }

    public function manualSupplyMovement(Supply $supply, string $type, float $quantity, string $justification, User $actor, ?int $authorizedById): SupplyMovement
    {
        return DB::transaction(function () use ($supply, $type, $quantity, $justification, $actor, $authorizedById): SupplyMovement {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            $before = (float) $supply->stock_quantity;
            $delta = match ($type) {
                'entry' => abs($quantity),
                'exit' => -abs($quantity),
                'adjustment' => $quantity,
                default => throw ValidationException::withMessages(['movement_type' => 'Tipo de movimiento no permitido.']),
            };
            $after = round($before + $delta, 2);
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => 'El movimiento supera la existencia disponible.']);
            }
            $reserved = (float) $supply->ticketUsages()->where('status', 'reserved')->sum('quantity_reserved');
            if ($after < $reserved) {
                throw ValidationException::withMessages(['quantity' => 'La existencia resultante no puede ser menor que la cantidad reservada.']);
            }

            $supply->update(['stock_quantity' => $after, 'updated_by_id' => $actor->id]);

            return $supply->movements()->create([
                'movement_type' => $type,
                'quantity_delta' => $delta,
                'balance_before' => $before,
                'balance_after' => $after,
                'justification' => $this->clean($justification),
                'responsible_id' => $actor->id,
                'authorized_by_id' => $authorizedById,
            ]);
        });
    }

    public function reserveSupply(Ticket $ticket, Supply $supply, float $quantity, ?string $notes, User $actor): TicketSupplyUsage
    {
        return DB::transaction(function () use ($ticket, $supply, $quantity, $notes, $actor): TicketSupplyUsage {
            $supply = Supply::whereKey($supply->id)->lockForUpdate()->firstOrFail();
            $usage = TicketSupplyUsage::where('ticket_id', $ticket->id)->where('supply_id', $supply->id)->lockForUpdate()->first();
            if ($usage && $usage->status === 'consumed') {
                throw ValidationException::withMessages(['supply_id' => 'Este insumo ya fue consumido para el ticket.']);
            }

            $reservedByOthers = (float) $supply->ticketUsages()->where('status', 'reserved')
                ->when($usage, fn ($query) => $query->where('id', '!=', $usage->id))->sum('quantity_reserved');
            if ($quantity > (float) $supply->stock_quantity - $reservedByOthers) {
                throw ValidationException::withMessages(['quantity' => 'La cantidad supera la existencia disponible no reservada.']);
            }

            return TicketSupplyUsage::updateOrCreate(
                ['ticket_id' => $ticket->id, 'supply_id' => $supply->id],
                [
                    'quantity_reserved' => $quantity,
                    'quantity_consumed' => null,
                    'status' => 'reserved',
                    'notes' => $this->clean($notes),
                    'recorded_by_id' => $actor->id,
                    'consumed_by_id' => null,
                    'consumed_at' => null,
                    'released_by_id' => null,
                    'released_at' => null,
                ],
            );
        });
    }

    public function releaseUsage(TicketSupplyUsage $usage, User $actor): void
    {
        DB::transaction(function () use ($usage, $actor): void {
            $usage = TicketSupplyUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
            if ($usage->status !== 'reserved') {
                throw ValidationException::withMessages(['usage' => 'Solo se pueden liberar reservas pendientes.']);
            }
            $usage->update(['status' => 'released', 'released_by_id' => $actor->id, 'released_at' => now()]);
        });
    }

    public function consumeForTicket(Ticket $ticket, User $actor): int
    {
        return DB::transaction(function () use ($ticket, $actor): int {
            $usages = TicketSupplyUsage::where('ticket_id', $ticket->id)->where('status', 'reserved')
                ->orderBy('id')->lockForUpdate()->get();
            $count = 0;

            foreach ($usages as $usage) {
                $supply = Supply::whereKey($usage->supply_id)->lockForUpdate()->firstOrFail();
                $quantity = (float) $usage->quantity_reserved;
                $before = (float) $supply->stock_quantity;
                if ($quantity > $before) {
                    throw ValidationException::withMessages([
                        'supplies' => 'No hay existencia suficiente de '.$supply->name.' para confirmar la solución.',
                    ]);
                }
                $after = round($before - $quantity, 2);
                $supply->update(['stock_quantity' => $after, 'updated_by_id' => $actor->id]);
                $movement = $supply->movements()->create([
                    'ticket_id' => $ticket->id,
                    'ticket_supply_usage_id' => $usage->id,
                    'movement_type' => 'consumption',
                    'quantity_delta' => -$quantity,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'justification' => 'Consumo confirmado al aceptar la solución del ticket '.$ticket->number.'.',
                    'responsible_id' => $actor->id,
                    'authorized_by_id' => $actor->id,
                ]);
                $usage->update([
                    'quantity_consumed' => $quantity,
                    'status' => 'consumed',
                    'consumed_by_id' => $actor->id,
                    'consumed_at' => now(),
                ]);
                $count++;
            }

            return $count;
        });
    }

    public function releaseTicketReservations(Ticket $ticket, User $actor): int
    {
        return TicketSupplyUsage::where('ticket_id', $ticket->id)->where('status', 'reserved')->update([
            'status' => 'released', 'released_by_id' => $actor->id, 'released_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}
