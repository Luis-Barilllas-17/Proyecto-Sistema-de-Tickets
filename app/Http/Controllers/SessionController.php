<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function destroy(Request $request, string $session): RedirectResponse
    {
        $record = DB::table('sessions')
            ->where('id', $session)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $record) {
            return back()->withErrors(['session' => 'La sesión ya no se encuentra activa.']);
        }

        $isCurrent = hash_equals($request->session()->getId(), $session);
        DB::table('sessions')->where('id', $session)->delete();
        SecurityEvent::record('session_revoked', $request, $request->user(), ['self_service' => true]);

        if ($isCurrent) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'La sesión actual fue finalizada.');
        }

        return back()->with('success', 'La sesión seleccionada fue finalizada.');
    }
}
