<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

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
        ]);
    }
}
