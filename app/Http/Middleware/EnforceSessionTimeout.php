<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = $request->session()->get('security_last_activity');
        $timeout = config('security.session_timeout_minutes') * 60;

        if ($lastActivity && now()->timestamp - $lastActivity > $timeout) {
            $user = $request->user();
            SecurityEvent::record('logout_inactivity', $request, $user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'La sesión se cerró automáticamente por inactividad.');
        }

        $request->session()->put('security_last_activity', now()->timestamp);

        return $next($request);
    }
}
