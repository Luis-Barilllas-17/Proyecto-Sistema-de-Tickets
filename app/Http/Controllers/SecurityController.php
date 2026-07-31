<?php

namespace App\Http\Controllers;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(): View
    {
        return view('security.index', [
            'users' => User::orderByDesc('is_super_admin')->orderBy('name')->get(),
            'sessions' => DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select('sessions.*', 'users.name', 'users.email')
                ->whereNotNull('sessions.user_id')
                ->orderByDesc('last_activity')
                ->get(),
            'events' => SecurityEvent::with('user')->latest()->limit(50)->get(),
        ]);
    }

    public function editPassword(User $user): View
    {
        return view('security.reset-password', compact('user'));
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()->symbols()],
        ], [
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'password_changed_at' => now(),
            'force_password_change' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        SecurityEvent::record('password_admin_reset', $request, $user, [
            'performed_by' => $request->user()->id,
        ]);

        return redirect()->route('security.index')->with('success', 'Contraseña restablecida. El usuario deberá cambiarla al ingresar.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
        ]);

        if ($user->is($request->user()) && $validated['status'] !== 'active') {
            return back()->withErrors(['status' => 'No puedes desactivar tu propia cuenta administrativa.']);
        }

        $user->forceFill([
            'status' => $validated['status'],
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        if ($validated['status'] !== 'active') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        SecurityEvent::record('account_'.$validated['status'], $request, $user, [
            'performed_by' => $request->user()->id,
        ]);

        $labels = [
            'active' => 'activada',
            'suspended' => 'suspendida',
            'inactive' => 'desactivada',
        ];

        return back()->with('success', 'Cuenta '.$labels[$validated['status']].' correctamente.');
    }

    public function unlock(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_failed_login_at' => null,
        ])->save();

        SecurityEvent::record('account_unlocked', $request, $user, [
            'performed_by' => $request->user()->id,
        ]);

        return back()->with('success', 'La cuenta fue desbloqueada y puede intentar ingresar nuevamente.');
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $record = DB::table('sessions')->where('id', $session)->first();

        if (! $record) {
            return back()->withErrors(['session' => 'La sesión ya no se encuentra activa.']);
        }

        $owner = $record->user_id ? User::find($record->user_id) : null;
        DB::table('sessions')->where('id', $session)->delete();
        SecurityEvent::record('session_revoked', $request, $owner, [
            'performed_by' => $request->user()->id,
        ]);

        return back()->with('success', 'La sesión seleccionada fue finalizada.');
    }
}
