@extends('layouts.guest')

@section('title', 'Inicio de sesión')

@section('content')
    <div class="auth-heading">
        <span class="auth-kicker">Acceso institucional</span>
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

        <button type="submit" class="button button--primary button--full">
            Ingresar al sistema
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <div class="auth-note">
        <span class="auth-note__shield" aria-hidden="true">✓</span>
        <p><strong>Conexión segura</strong><br>Los accesos e intentos fallidos quedan registrados.</p>
    </div>
@endsection
