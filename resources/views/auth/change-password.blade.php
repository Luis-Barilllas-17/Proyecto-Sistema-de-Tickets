@extends('layouts.app')

@section('title', 'Cambiar contraseña')
@section('section-title', 'Cambiar contraseña')

@section('content')
    <div class="page-heading page-heading--compact">
        <div>
            <span class="eyebrow">Protección de cuenta</span>
            <h1>{{ $forced ? 'Establece una contraseña personal' : 'Actualiza tu contraseña' }}</h1>
            <p>{{ $forced ? 'El administrador restableció tu acceso. Debes crear una contraseña nueva para continuar.' : 'Al actualizarla, cerraremos las demás sesiones abiertas de tu cuenta.' }}</p>
        </div>
    </div>

    <div class="password-page-grid">
        <section class="panel form-panel">
            <div class="panel__header">
                <div>
                    <span class="panel__eyebrow">Credenciales</span>
                    <h2>Nueva contraseña</h2>
                </div>
                <span class="panel-badge panel-badge--secure">Protegido</span>
            </div>

            <form action="{{ route('password.update') }}" method="POST" class="stack-form">
                @csrf
                @method('PUT')

                @unless ($forced)
                    <div class="field">
                        <label for="current_password">Contraseña actual</label>
                        <div class="input-shell">
                            <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required data-password-input>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
                        </div>
                    </div>
                @endunless

                <div class="field">
                    <label for="password">Nueva contraseña</label>
                    <div class="input-shell">
                        <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                        <input id="password" name="password" type="password" autocomplete="new-password" required data-password-input>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirmar nueva contraseña</label>
                    <div class="input-shell">
                        <span class="input-prefix input-prefix--lock" aria-hidden="true"></span>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-input>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Mostrar contraseña"><span>Ver</span></button>
                    </div>
                </div>

                <div class="form-actions">
                    @unless ($forced)
                        <a href="{{ route('dashboard') }}" class="button button--ghost">Cancelar</a>
                    @endunless
                    <button class="button button--primary" type="submit">Guardar contraseña <span aria-hidden="true">→</span></button>
                </div>
            </form>
        </section>

        <aside class="panel password-guidance">
            <span class="guidance-mark" aria-hidden="true">✓</span>
            <span class="panel__eyebrow">Recomendaciones</span>
            <h2>Una contraseña sólida</h2>
            <ul class="check-list">
                <li><span>✓</span> Al menos 10 caracteres</li>
                <li><span>✓</span> Una letra mayúscula y una minúscula</li>
                <li><span>✓</span> Un número y un símbolo</li>
                <li><span>✓</span> Diferente a contraseñas anteriores</li>
            </ul>
            <p>Nunca compartas tu contraseña por correo, chat o llamada.</p>
        </aside>
    </div>
@endsection
