<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Equipment;
use App\Models\MaintenanceOrder;
use App\Models\SecurityEvent;
use App\Models\Supply;
use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketCategory;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = [
        'tickets' => 'Tickets',
        'sla' => 'Cumplimiento SLA',
        'technicians' => 'Carga de técnicos',
        'satisfaction' => 'Satisfacción',
        'recurring' => 'Problemas recurrentes',
        'equipment' => 'Equipos',
        'supplies' => 'Consumo de insumos',
        'maintenance' => 'Mantenimientos',
        'audit' => 'Auditoría',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $this->authorizeReportType($request->user(), $filters['report']);
        $query = $this->ticketQuery($request->user(), $filters);
        $tickets = (clone $query)->with(['status', 'priority', 'category', 'subcategory', 'currentGroup', 'currentAssignee', 'review'])
            ->limit(10000)->get();
        $dataset = $this->dataset($filters['report'], $filters, $tickets, 250);

        return view('reports.index', [
            'filters' => $filters,
            'reportTypes' => self::TYPES,
            'dataset' => $dataset,
            'metrics' => $this->metrics($tickets),
            'statuses' => TicketStatus::where('is_active', true)->orderBy('sort_order')->get(),
            'priorities' => TicketPriority::where('is_active', true)->orderByDesc('weight')->get(),
            'categories' => TicketCategory::where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::where('type', 'it')->where('is_active', true)->orderBy('name')->get(),
            'groups' => TechnicalGroup::where('is_active', true)->orderBy('name')->get(),
            'technicians' => User::where('status', 'active')->whereHas('roles', fn ($query) => $query
                ->whereIn('slug', ['jefatura-it', 'encargado-area-it', 'tecnico-it', 'programador-it']))->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);
        $filters = $this->filters($request);
        $this->authorizeReportType($request->user(), $filters['report']);
        $tickets = $this->ticketQuery($request->user(), $filters)
            ->with(['status', 'priority', 'category', 'subcategory', 'currentGroup', 'currentAssignee', 'review'])
            ->limit(10000)->get();
        $dataset = $this->dataset($filters['report'], $filters, $tickets, 10000);
        $filename = 'zolic-'.$filters['report'].'-'.$filters['start_date'].'-'.$filters['end_date'].'.csv';

        return response()->streamDownload(function () use ($dataset): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_values($dataset['columns']));
            foreach ($dataset['rows'] as $row) {
                fputcsv($stream, collect(array_keys($dataset['columns']))->map(fn ($key) => $this->safeCsv($row[$key] ?? ''))->all());
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function filters(Request $request): array
    {
        $request->merge([
            'report' => $request->input('report', 'tickets'),
            'start_date' => $request->input('start_date', today()->subDays(29)->toDateString()),
            'end_date' => $request->input('end_date', today()->toDateString()),
        ]);

        return $request->validate([
            'report' => ['required', Rule::in(array_keys(self::TYPES))],
            'start_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'group_id' => ['nullable', 'integer', 'exists:it_groups,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'status_id' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
        ]);
    }

    private function ticketQuery(User $user, array $filters): Builder
    {
        $query = Ticket::query()->where('is_draft', false)
            ->whereBetween('created_at', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59'])
            ->when($filters['area_id'] ?? null, fn ($query, $id) => $query->where('current_it_area_id', $id))
            ->when($filters['group_id'] ?? null, fn ($query, $id) => $query->where('current_it_group_id', $id))
            ->when($filters['technician_id'] ?? null, fn ($query, $id) => $query->where('current_assignee_id', $id))
            ->when($filters['status_id'] ?? null, fn ($query, $id) => $query->where('ticket_status_id', $id))
            ->when($filters['priority_id'] ?? null, fn ($query, $id) => $query->where('ticket_priority_id', $id))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('ticket_category_id', $id));
        if (! $user->isSuperAdmin() && ! $user->hasPermission('tickets.view_all')) {
            $groupIds = $user->technicalGroups()->pluck('it_groups.id');
            $query->where(fn ($inner) => $inner->where('requester_id', $user->id)
                ->orWhere('current_assignee_id', $user->id)->orWhereIn('current_it_group_id', $groupIds));
        }

        return $query;
    }

    private function metrics(Collection $tickets): array
    {
        $responseTarget = $tickets->whereNotNull('response_due_at');
        $resolutionTarget = $tickets->whereNotNull('resolution_due_at');
        $reviews = $tickets->pluck('review')->filter();

        return [
            'tickets' => $tickets->count(),
            'open' => $tickets->filter(fn ($ticket) => ! $ticket->status?->is_final)->count(),
            'response_compliance' => $responseTarget->isEmpty() ? null : round(100 * $responseTarget->filter(fn ($ticket) => $ticket->first_response_at && $ticket->first_response_at->lte($ticket->response_due_at))->count() / $responseTarget->count(), 1),
            'resolution_compliance' => $resolutionTarget->isEmpty() ? null : round(100 * $resolutionTarget->filter(fn ($ticket) => $ticket->solved_at && $ticket->solved_at->lte($ticket->resolution_due_at))->count() / $resolutionTarget->count(), 1),
            'rating' => $reviews->whereNotNull('rating')->avg('rating'),
            'reopened' => $tickets->sum('reopen_count'),
        ];
    }

    private function dataset(string $type, array $filters, Collection $tickets, int $limit): array
    {
        return match ($type) {
            'sla' => $this->slaDataset($tickets, $limit),
            'technicians' => $this->technicianDataset($tickets, $filters, $limit),
            'satisfaction' => $this->satisfactionDataset($tickets, $limit),
            'recurring' => $this->recurringDataset($tickets, $limit),
            'equipment' => $this->equipmentDataset($limit),
            'supplies' => $this->supplyDataset($filters, $limit),
            'maintenance' => $this->maintenanceDataset($filters, $limit),
            'audit' => $this->auditDataset($filters, $tickets, $limit),
            default => $this->ticketDataset($tickets, $limit),
        };
    }

    private function ticketDataset(Collection $tickets, int $limit): array
    {
        return ['columns' => [
            'number' => 'Ticket', 'created' => 'Fecha', 'title' => 'Asunto', 'category' => 'Categoría',
            'priority' => 'Prioridad', 'status' => 'Estado', 'group' => 'Grupo', 'assignee' => 'Responsable',
            'resolution_due' => 'Objetivo de resolución',
        ], 'rows' => $tickets->take($limit)->map(fn ($ticket) => [
            'number' => $ticket->number, 'created' => $ticket->created_at->format('d/m/Y H:i'), 'title' => $ticket->title,
            'category' => $ticket->category?->name, 'priority' => $ticket->priority?->name, 'status' => $ticket->status?->name,
            'group' => $ticket->currentGroup?->name, 'assignee' => $ticket->currentAssignee?->name,
            'resolution_due' => $ticket->resolution_due_at?->format('d/m/Y H:i'),
        ])->values()];
    }

    private function slaDataset(Collection $tickets, int $limit): array
    {
        $rows = $tickets->groupBy(fn ($ticket) => $ticket->priority?->name ?: 'Sin prioridad')->map(function ($items, $priority) {
            $response = $items->whereNotNull('response_due_at');
            $resolution = $items->whereNotNull('resolution_due_at');

            return [
                'priority' => $priority, 'tickets' => $items->count(),
                'response_met' => $response->filter(fn ($ticket) => $ticket->first_response_at && $ticket->first_response_at->lte($ticket->response_due_at))->count(),
                'response_breached' => $response->filter(fn ($ticket) => ! $ticket->first_response_at || $ticket->first_response_at->gt($ticket->response_due_at))->count(),
                'resolution_met' => $resolution->filter(fn ($ticket) => $ticket->solved_at && $ticket->solved_at->lte($ticket->resolution_due_at))->count(),
                'resolution_breached' => $resolution->filter(fn ($ticket) => ! $ticket->solved_at || $ticket->solved_at->gt($ticket->resolution_due_at))->count(),
            ];
        })->values()->take($limit);

        return ['columns' => ['priority' => 'Prioridad', 'tickets' => 'Tickets', 'response_met' => 'Respuesta cumplida', 'response_breached' => 'Respuesta incumplida', 'resolution_met' => 'Resolución cumplida', 'resolution_breached' => 'Resolución incumplida'], 'rows' => $rows];
    }

    private function technicianDataset(Collection $tickets, array $filters, int $limit): array
    {
        $minutes = TicketActivity::whereBetween('activity_date', [$filters['start_date'], $filters['end_date']])
            ->whereIn('ticket_id', $tickets->pluck('id'))->selectRaw('user_id, SUM(minutes_spent) total')->groupBy('user_id')->pluck('total', 'user_id');
        $users = User::whereIn('id', $tickets->pluck('current_assignee_id')->filter()->unique())->get()->keyBy('id');
        $rows = $tickets->whereNotNull('current_assignee_id')->groupBy('current_assignee_id')->map(function ($items, $id) use ($users, $minutes) {
            return [
                'technician' => $users->get($id)?->name, 'assigned' => $items->count(),
                'open' => $items->filter(fn ($ticket) => ! $ticket->status?->is_final)->count(),
                'solved' => $items->whereNotNull('solved_at')->count(),
                'overdue' => $items->filter(fn ($ticket) => ! $ticket->solved_at && $ticket->resolution_due_at?->isPast())->count(),
                'hours' => round(((int) ($minutes[$id] ?? 0)) / 60, 2),
            ];
        })->sortByDesc('assigned')->values()->take($limit);

        return ['columns' => ['technician' => 'Técnico', 'assigned' => 'Asignados', 'open' => 'Abiertos', 'solved' => 'Solucionados', 'overdue' => 'Vencidos', 'hours' => 'Horas registradas'], 'rows' => $rows];
    }

    private function satisfactionDataset(Collection $tickets, int $limit): array
    {
        $rows = $tickets->pluck('review')->filter()->take($limit)->map(fn ($review) => [
            'ticket' => $review->ticket?->number, 'decision' => $review->decision === 'accepted' ? 'Aceptado' : 'Rechazado',
            'rating' => $review->rating, 'comment' => $review->comment, 'date' => $review->confirmed_at?->format('d/m/Y H:i'),
        ])->values();

        return ['columns' => ['ticket' => 'Ticket', 'decision' => 'Decisión', 'rating' => 'Calificación', 'comment' => 'Comentario', 'date' => 'Fecha'], 'rows' => $rows];
    }

    private function recurringDataset(Collection $tickets, int $limit): array
    {
        $rows = $tickets->groupBy(fn ($ticket) => ($ticket->category?->name ?: 'Sin categoría').'|'.($ticket->subcategory?->name ?: 'Sin subcategoría'))
            ->map(function ($items, $key) {
                [$category, $subcategory] = explode('|', $key, 2);

                return ['category' => $category, 'subcategory' => $subcategory, 'tickets' => $items->count(), 'reopened' => $items->sum('reopen_count')];
            })->sortByDesc('tickets')->values()->take($limit);

        return ['columns' => ['category' => 'Categoría', 'subcategory' => 'Subcategoría', 'tickets' => 'Tickets', 'reopened' => 'Reaperturas'], 'rows' => $rows];
    }

    private function equipmentDataset(int $limit): array
    {
        $rows = Equipment::with(['area', 'department', 'responsible'])->withCount('ticketLinks')->orderBy('inventory_code')->limit($limit)->get()->map(fn ($item) => [
            'code' => $item->inventory_code, 'name' => $item->name, 'type' => $item->type_label, 'status' => $item->status_label,
            'area' => $item->area?->name, 'department' => $item->department?->name, 'responsible' => $item->responsible?->name,
            'tickets' => $item->ticket_links_count,
        ]);

        return ['columns' => ['code' => 'Código', 'name' => 'Equipo', 'type' => 'Tipo', 'status' => 'Estado', 'area' => 'Área', 'department' => 'Departamento', 'responsible' => 'Responsable', 'tickets' => 'Tickets vinculados'], 'rows' => $rows];
    }

    private function supplyDataset(array $filters, int $limit): array
    {
        $rows = Supply::withSum(['ticketUsages as consumed' => fn ($query) => $query->where('status', 'consumed')
            ->whereBetween('consumed_at', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59'])], 'quantity_consumed')
            ->orderByDesc('consumed')->limit($limit)->get()->map(fn ($item) => [
                'code' => $item->code, 'name' => $item->name, 'category' => $item->category,
                'stock' => $item->stock_quantity, 'minimum' => $item->minimum_quantity,
                'consumed' => $item->consumed ?? 0, 'unit' => $item->unit_label,
            ]);

        return ['columns' => ['code' => 'Código', 'name' => 'Insumo', 'category' => 'Categoría', 'stock' => 'Existencia', 'minimum' => 'Mínimo', 'consumed' => 'Consumo', 'unit' => 'Unidad'], 'rows' => $rows];
    }

    private function maintenanceDataset(array $filters, int $limit): array
    {
        $rows = MaintenanceOrder::with(['plan', 'equipment', 'assignedTo'])->whereBetween('scheduled_for', [$filters['start_date'], $filters['end_date']])
            ->orderByDesc('scheduled_for')->limit($limit)->get()->map(fn ($order) => [
                'number' => $order->number, 'date' => $order->scheduled_for->format('d/m/Y'), 'plan' => $order->plan->name,
                'equipment' => $order->equipment->inventory_code.' · '.$order->equipment->name,
                'technician' => $order->assignedTo?->name, 'status' => $order->status_label, 'result' => $order->result_label,
            ]);

        return ['columns' => ['number' => 'Orden', 'date' => 'Programación', 'plan' => 'Plan', 'equipment' => 'Equipo', 'technician' => 'Responsable', 'status' => 'Estado', 'result' => 'Resultado'], 'rows' => $rows];
    }

    private function auditDataset(array $filters, Collection $tickets, int $limit): array
    {
        $ticketEvents = TicketHistory::with(['ticket', 'user'])->whereIn('ticket_id', $tickets->pluck('id'))
            ->whereBetween('created_at', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59'])->get()->map(fn ($event) => [
                'date' => $event->created_at->format('d/m/Y H:i:s'), 'source' => 'Ticket '.$event->ticket?->number,
                'event' => $event->event, 'user' => $event->user?->email, 'detail' => $event->description, 'ip' => $event->ip_address,
            ]);
        $securityEvents = SecurityEvent::with('user')->whereBetween('created_at', [$filters['start_date'].' 00:00:00', $filters['end_date'].' 23:59:59'])
            ->get()->map(fn ($event) => [
                'date' => $event->created_at->format('d/m/Y H:i:s'), 'source' => 'Seguridad', 'event' => $event->event,
                'user' => $event->email ?: $event->user?->email, 'detail' => json_encode($event->details, JSON_UNESCAPED_UNICODE), 'ip' => $event->ip_address,
            ]);

        return ['columns' => ['date' => 'Fecha', 'source' => 'Origen', 'event' => 'Evento', 'user' => 'Usuario', 'detail' => 'Detalle', 'ip' => 'IP'],
            'rows' => $ticketEvents->merge($securityEvents)->sortByDesc('date')->values()->take($limit)];
    }

    private function authorizeReportType(User $user, string $type): void
    {
        if (in_array($type, ['equipment', 'supplies', 'maintenance', 'audit'], true)) {
            abort_unless($user->isSuperAdmin() || $user->hasPermission('tickets.view_all'), 403);
        }
    }

    private function safeCsv(mixed $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', (string) $value);

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
