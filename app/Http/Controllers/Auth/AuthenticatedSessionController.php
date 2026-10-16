<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\TurnstileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, TurnstileService $turnstile): RedirectResponse
    {
        $rules = [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];

        if ($turnstile->enabled()) {
            $rules['cf-turnstile-response'] = ['required', 'string', 'max:2048'];
        }

        $credentials = $request->validate($rules, [
            'email.required' => 'Ingresa tu correo institucional.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
            'cf-turnstile-response.required' => 'Completa la verificación de seguridad.',
            'cf-turnstile-response.max' => 'La verificación de seguridad no es válida.',
        ]);

        if ($turnstile->enabled() && ! $turnstile->verify($credentials['cf-turnstile-response'], $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'No fue posible validar la verificación de seguridad. Inténtalo nuevamente.',
            ]);
        }

        $email = Str::lower(trim($credentials['email']));
        $this->ensureInstitutionalEmail($email);

        $key = Str::transliterate($email.'|'.$request->ip());
        $user = User::where('email', $email)->first();

        if (RateLimiter::tooManyAttempts($key, config('security.max_login_attempts'))) {
            SecurityEvent::record('login_blocked', $request, $user, email: $email);
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Intenta nuevamente en '.max(1, (int) ceil($seconds / 60)).' minuto(s).',
            ]);
        }

        if ($user?->locked_until?->isPast()) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        if ($user?->isLocked()) {
            SecurityEvent::record('login_blocked', $request, $user);

            throw ValidationException::withMessages([
                'email' => 'La cuenta está bloqueada temporalmente. Intenta nuevamente después de las '.$user->locked_until->format('H:i').'.',
            ]);
        }

        $passwordIsValid = $user && Hash::check($credentials['password'], $user->password);

        if (! $passwordIsValid || ! $user?->isActive()) {
            RateLimiter::hit($key, config('security.lockout_minutes') * 60);

            if ($user) {
                if ($passwordIsValid && ! $user->isActive()) {
                    SecurityEvent::record('login_blocked', $request, $user, ['reason' => 'inactive_account']);
                } else {
                    $attempts = $user->failed_login_attempts + 1;
                    $locked = $attempts >= config('security.max_login_attempts');

                    $user->forceFill([
                        'failed_login_attempts' => $locked ? 0 : $attempts,
                        'last_failed_login_at' => now(),
                        'locked_until' => $locked ? now()->addMinutes(config('security.lockout_minutes')) : null,
                    ])->save();

                    SecurityEvent::record($locked ? 'account_locked' : 'login_failed', $request, $user, [
                        'attempt' => $attempts,
                    ]);
                }
            } else {
                SecurityEvent::record('login_failed', $request, email: $email);
            }

            throw ValidationException::withMessages([
                'email' => $passwordIsValid
                    ? 'Tu cuenta no está activa. Comunícate con el administrador del sistema.'
                    : 'Las credenciales ingresadas no son correctas.',
            ]);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('security_last_activity', now()->timestamp);

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        SecurityEvent::record('login_success', $request, $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        SecurityEvent::record('logout', $request, $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Tu sesión se cerró correctamente.');
    }

    private function ensureInstitutionalEmail(string $email): void
    {
        $domains = config('security.institutional_domains', []);

        if ($domains !== [] && ! Str::endsWith($email, array_map(fn ($domain) => '@'.$domain, $domains))) {
            throw ValidationException::withMessages([
                'email' => 'Utiliza tu correo institucional autorizado.',
            ]);
        }
    }
}
