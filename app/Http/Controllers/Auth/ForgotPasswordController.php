<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Ingresa tu correo institucional.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $email = Str::lower(trim($request->string('email')->toString()));
        $user = User::where('email', $email)->first();

        if ($user?->isActive()) {
            Password::sendResetLink(['email' => $email]);
            SecurityEvent::record('password_reset_requested', $request, $user);
        }

        return back()->with(
            'status',
            'Si el correo corresponde a una cuenta activa, recibirás un enlace de recuperación en los próximos minutos.'
        );
    }
}
