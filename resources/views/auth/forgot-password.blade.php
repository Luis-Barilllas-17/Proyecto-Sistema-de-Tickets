@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <a href="{{ route('login') }}" class="back-link"><span aria-hidden="true">←</span> Volver al inicio</a>

    <div class="auth-heading">
        <span class="auth-kicker">Recuperación segura</span>
        <h2>Recupera tu acceso</h2>
        <p>Escribe tu correo institucional. Enviaremos un enlace seguro para crear una nueva contraseña.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
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
                    autocomplete="email"
                    required
                >
            </div>
        </div>

        <button type="submit" class="button button--primary button--full">
            Enviar enlace de recuperación
            <span aria-hidden="true">→</span>
        </button>
    </form>

    <div class="auth-note auth-note--blue">
        <span class="auth-note__shield" aria-hidden="true">i</span>
        <p>El enlace tendrá una vigencia de <strong>{{ config('auth.passwords.users.expire') }} minutos</strong> y solo podrá utilizarse una vez.</p>
    </div>
@endsection
