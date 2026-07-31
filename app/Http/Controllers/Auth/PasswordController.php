<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password', [
            'forced' => $request->user()->force_password_change,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'password' => ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()->symbols()],
        ];

        if (! $user->force_password_change) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules, [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'password_changed_at' => now(),
            'force_password_change' => false,
        ])->save();

        SecurityEvent::record('password_changed', $request, $user);
        $request->session()->regenerate();
        $request->session()->put('security_last_activity', now()->timestamp);

        return redirect()->route('dashboard')->with('success', 'Tu contraseña se actualizó y las demás sesiones fueron cerradas.');
    }
}
