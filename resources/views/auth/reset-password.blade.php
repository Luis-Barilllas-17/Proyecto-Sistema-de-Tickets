@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <div class="auth-heading">
        <span class="auth-kicker">Nuevo acceso</span>
        <h2>Crea una contraseña segura</h2>
        <p>Utiliza una combinación única que no emplees en otros servicios.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">Correo institucional</label>
            <div class="input-shell">
                <span class="input-prefix" aria-hidden="true">@</span>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" required readonly>
            </div>
        </div>

        <div class="field">
            <label for="password">Nueva contraseña</label>
            <div class="input-shell">
                <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                <input id="password" name="password" type="password" autocomplete="new-password" required data-password-input>
                <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
            </div>
            <span class="field__hint">Mínimo 10 caracteres, mayúscula, minúscula, número y símbolo.</span>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <div class="input-shell">
                <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-input>
                <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
            </div>
        </div>

        <button type="submit" class="button button--primary button--full">
            Restablecer contraseña
            <span aria-hidden="true">→</span>
        </button>
    </form>
@endsection
