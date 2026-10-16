@extends('layouts.guest')

@section('title', 'Inicio de sesión')

@push('head')
    @if (config('turnstile.enabled'))
        <link rel="preconnect" href="https://challenges.cloudflare.com">
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endpush

@section('content')
    <div class="auth-heading">
        <h2>Bienvenido de nuevo</h2>
        <p>Ingresa tus credenciales para acceder al sistema.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
        @csrf

        <div class="field">
            <label for="email">Correo institucional</label>
            <div class="input-shell {{ $errors->has('email') ? 'has-error' : '' }}">
                <span class="input-prefix" aria-hidden="true">@</span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    placeholder="nombre@zolicguate.com"
                    autocomplete="username"
                    required
                >
            </div>
        </div>

        <div class="field">
            <div class="field__label-row">
                <label for="password">Contraseña</label>
                <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
            </div>
            <div class="input-shell {{ $errors->has('password') ? 'has-error' : '' }}">
                <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                    data-password-input
                >
                <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña">
                    <span aria-hidden="true">Ver</span>
                </button>
            </div>
        </div>

        @if (config('turnstile.enabled'))
            <div class="turnstile-field">
                <span class="turnstile-field__label">Verificación de seguridad</span>
                <div
                    class="cf-turnstile"
                    data-sitekey="{{ config('turnstile.site_key') }}"
                    data-theme="light"
                    data-size="flexible"
                    data-language="es"
                    data-action="{{ config('turnstile.expected_action') }}"
                    aria-label="Verificación de seguridad de Cloudflare Turnstile"
                ></div>
                @error('cf-turnstile-response')
                    <span class="field-error" role="alert">{{ $message }}</span>
                @enderror
            </div>
        @endif

        <button type="submit" class="button button--primary button--full">
            Ingresar al sistema
            <span aria-hidden="true">→</span>
        </button>
    </form>

@endsection
