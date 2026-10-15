<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $institutionalDomains = config('security.institutional_domains', []);
        $emailDomain = str($user->email)->afterLast('@')->lower()->toString();
        $securityChecks = [
            $user->isActive(),
            ! $user->isLocked(),
            $user->email_verified_at !== null,
            in_array($emailDomain, $institutionalDomains, true),
            config('security.max_login_attempts') > 0,
            config('security.session_timeout_minutes') > 0,
        ];
        $securityControlsPassed = count(array_filter($securityChecks));
        $securityControlsTotal = count($securityChecks);
        $requesterPortal = $user->hasRole('usuario-solicitante');
        $requesterTickets = Ticket::query()
            ->where('requester_id', $user->id);

        return view('dashboard', [
            'recentEvents' => SecurityEvent::where('user_id', $user->id)->latest()->limit(6)->get(),
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get(),
            'activeUsers' => $user->is_super_admin ? User::where('status', 'active')->count() : null,
            'incidents' => $user->is_super_admin
                ? SecurityEvent::whereIn('event', ['login_failed', 'login_blocked', 'account_locked'])
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count()
                : SecurityEvent::where('user_id', $user->id)
                    ->whereIn('event', ['login_failed', 'login_blocked', 'account_locked'])
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            'securityControlsPassed' => $securityControlsPassed,
            'securityControlsTotal' => $securityControlsTotal,
            'securityScore' => (int) round(($securityControlsPassed / $securityControlsTotal) * 100),
            'requesterPortal' => $requesterPortal,
            'requesterMetrics' => $requesterPortal ? [
                'total' => (clone $requesterTickets)->count(),
                'drafts' => (clone $requesterTickets)->where('is_draft', true)->count(),
                'active' => (clone $requesterTickets)->where('is_draft', false)
                    ->whereHas('status', fn ($query) => $query->where('is_final', false)->whereNotIn('slug', ['solucionado']))
                    ->count(),
                'resolved' => (clone $requesterTickets)->whereHas('status', fn ($query) => $query->whereIn('slug', ['solucionado', 'cerrado']))
                    ->count(),
            ] : null,
            'requesterRecentTickets' => $requesterPortal
                ? (clone $requesterTickets)->with(['category', 'status', 'priority'])->latest('updated_at')->limit(6)->get()
                : collect(),
        ]);
    }
}
