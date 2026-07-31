@extends('layouts.app')

@section('title', 'Restablecimiento administrativo')
@section('section-title', 'Control de acceso')

@section('content')
    <div class="page-heading page-heading--compact">
        <div>
            <a href="{{ route('security.index') }}" class="back-link"><span aria-hidden="true">←</span> Volver al control de acceso</a>
            <span class="eyebrow">RF-003 · Restablecimiento administrativo</span>
            <h1>Restablecer contraseña</h1>
            <p>Define una clave temporal para el usuario seleccionado.</p>
        </div>
    </div>

    <div class="password-page-grid">
        <section class="panel form-panel">
            <div class="selected-user">
                <span class="avatar">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                <div>
                    <span>Cuenta seleccionada</span>
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->email }}</small>
                </div>
                <span class="status-badge status-badge--{{ $user->status }}">{{ $user->status === 'active' ? 'Activa' : ($user->status === 'suspended' ? 'Suspendida' : 'Inactiva') }}</span>
            </div>

            <form action="{{ route('security.password.update', $user) }}" method="POST" class="stack-form">
                @csrf
                @method('PUT')

                <div class="field">
                    <label for="password">Contraseña temporal</label>
                    <div class="input-shell">
                        <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                        <input id="password" name="password" type="password" autocomplete="new-password" required data-password-input autofocus>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
                    </div>
                    <span class="field__hint">Mínimo 10 caracteres, mayúscula, minúscula, número y símbolo.</span>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar contraseña temporal</label>
                    <div class="input-shell">
                        <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-input>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
                    </div>
                </div>

                <div class="admin-reset-notice">
                    <span aria-hidden="true">!</span>
                    <p>Se cerrarán todas las sesiones del usuario y deberá crear una contraseña personal en su próximo ingreso.</p>
                </div>

                <div class="form-actions">
                    <a href="{{ route('security.index') }}" class="button button--ghost">Cancelar</a>
                    <button class="button button--primary" type="submit">Restablecer acceso <span aria-hidden="true">→</span></button>
                </div>
            </form>
        </section>

        <aside class="panel password-guidance password-guidance--admin">
            <span class="guidance-mark guidance-mark--orange" aria-hidden="true">!</span>
            <span class="panel__eyebrow">Medida administrativa</span>
            <h2>Antes de continuar</h2>
            <ul class="step-list">
                <li><span>1</span><p><strong>Confirma la identidad</strong>Valida que la solicitud provenga del usuario.</p></li>
                <li><span>2</span><p><strong>Entrega por canal seguro</strong>No envíes la clave temporal en texto público.</p></li>
                <li><span>3</span><p><strong>Revisa la bitácora</strong>El cambio quedará registrado automáticamente.</p></li>
            </ul>
        </aside>
    </div>
@endsection
