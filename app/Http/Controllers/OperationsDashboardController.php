<?php

namespace App\Http\Controllers;

use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationsDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['general', 'mine', 'group', 'technician', 'unassigned'])],
            'group_id' => ['nullable', 'integer', 'exists:it_groups,id'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'status_id' => ['nullable', 'integer', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'sla' => ['nullable', Rule::in(['on_track', 'at_risk', 'overdue'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $base = $this->visibleOperationalQuery($user);
        $metricsBase = clone $base;
        $scope = $filters['scope'] ?? ($user->hasPermission('tickets.view_all') ? 'general' : 'mine');
        $query = $base->with([
            'requester', 'category', 'priority', 'status', 'currentArea', 'currentGroup', 'currentAssignee',
        ]);

        $query->when($scope === 'mine', fn (Builder $query) => $query->where('current_assignee_id', $user->id))
            ->when($scope === 'group', fn (Builder $query) => $query->where('current_it_group_id', $filters['group_id'] ?? -1))
            ->when($scope === 'technician', fn (Builder $query) => $query->where('current_assignee_id', $filters['technician_id'] ?? -1))
            ->when($scope === 'unassigned', fn (Builder $query) => $query->whereNull('current_assignee_id')->where(fn ($inner) => $inner
                ->whereNull('current_it_group_id')->orWhere('assignment_status', 'unassigned')))
            ->when($filters['status_id'] ?? null, fn (Builder $query, $id) => $query->where('ticket_status_id', $id))
            ->when($filters['priority_id'] ?? null, fn (Builder $query, $id) => $query->where('ticket_priority_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, $search) => $query->where(fn ($inner) => $inner
                ->where('number', 'like', '%'.$search.'%')->orWhere('title', 'like', '%'.$search.'%')));
        $this->applySlaFilter($query, $filters['sla'] ?? null);

        $techniciansQuery = User::query()->where('status', 'active')->whereHas('roles', fn ($query) => $query
            ->whereIn('slug', ['jefatura-it', 'encargado-area-it', 'tecnico-it', 'programador-it'])->where('is_active', true));
        if (! $user->hasPermission('tickets.view_all')) {
            $techniciansQuery->whereKey($user->id);
        }
        $technicians = $techniciansQuery->with('roles')->withCount([
            'assignedTickets as open_tickets_count' => fn ($query) => $query->where('is_draft', false)
                ->whereHas('status', fn ($status) => $status->where('is_final', false)),
            'assignedTickets as overdue_tickets_count' => fn ($query) => $query->whereNull('solved_at')
                ->where('resolution_due_at', '<', now())->whereHas('status', fn ($status) => $status->where('is_final', false)),
        ])->withSum([
            'ticketActivities as minutes_last_30_days' => fn ($query) => $query->where('activity_date', '>=', today()->subDays(29)),
        ], 'minutes_spent')->orderBy('name')->get();

        $visibleGroups = TechnicalGroup::where('is_active', true)->with('area');
        if (! $user->hasPermission('tickets.view_all')) {
            $visibleGroups->whereIn('id', $user->technicalGroups()->pluck('it_groups.id'));
        }

        return view('operations.index', [
            'tickets' => $query->orderByRaw('CASE WHEN resolution_due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('resolution_due_at')->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'metrics' => [
                'active' => (clone $metricsBase)->count(),
                'unassigned' => (clone $metricsBase)->whereNull('current_assignee_id')->count(),
                'pending_acceptance' => (clone $metricsBase)->where('assignment_status', 'pending')->count(),
                'overdue' => (clone $metricsBase)->whereNull('solved_at')->where('resolution_due_at', '<', now())->count(),
                'urgent' => (clone $metricsBase)->whereHas('priority', fn ($query) => $query->where('weight', '>=', 3))->count(),
            ],
            'groups' => $visibleGroups->orderBy('name')->get(),
            'technicians' => $technicians,
            'statuses' => TicketStatus::where('is_active', true)->where('is_final', false)->orderBy('sort_order')->get(),
            'priorities' => TicketPriority::where('is_active', true)->orderByDesc('weight')->get(),
            'filters' => $filters + ['scope' => $scope],
        ]);
    }

    private function visibleOperationalQuery(User $user): Builder
    {
        $query = Ticket::query()->where('is_draft', false)
            ->whereHas('status', fn ($status) => $status->where('is_final', false));
        if ($user->isSuperAdmin() || $user->hasPermission('tickets.view_all')) {
            return $query;
        }

        $groupIds = $user->technicalGroups()->pluck('it_groups.id');

        return $query->where(fn ($inner) => $inner->where('current_assignee_id', $user->id)
            ->orWhereIn('current_it_group_id', $groupIds));
    }

    private function applySlaFilter(Builder $query, ?string $sla): void
    {
        if ($sla === 'overdue') {
            $query->whereNull('solved_at')->where('resolution_due_at', '<', now());
        } elseif ($sla === 'at_risk') {
            $query->whereNull('solved_at')->whereBetween('resolution_due_at', [now(), now()->addHours(4)]);
        } elseif ($sla === 'on_track') {
            $query->where(fn ($inner) => $inner->whereNull('resolution_due_at')->orWhere('resolution_due_at', '>', now()->addHours(4)));
        }
    }
}
