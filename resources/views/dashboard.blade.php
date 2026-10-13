@extends('layouts.app')

@section('title', 'Resumen de seguridad')
@section('section-title', 'Seguridad y acceso')

@section('content')
    <div class="page-heading dashboard-welcome">
        <div>
            <span class="eyebrow">Seguridad de acceso</span>
            <h1>Hola, {{ str(auth()->user()->name)->before(' ') }}</h1>
            <p>Consulta el estado de tu cuenta, tus sesiones y la actividad reciente.</p>
        </div>
        @if (auth()->user()->is_super_admin)
            <a href="{{ route('security.index') }}" class="button button--primary">
                Administrar accesos
                <span aria-hidden="true">→</span>
            </a>
        @endif
    </div>

    <section class="metric-grid" aria-label="Indicadores de seguridad">
        <article class="metric-card metric-card--blue">
            <div class="metric-card__icon"><span aria-hidden="true">✓</span></div>
            <div>
                <span>Estado de cuenta</span>
                <strong>Activa</strong>
                <small>Acceso autorizado</small>
            </div>
        </article>

        <article class="metric-card metric-card--teal">
            <div class="metric-card__icon"><span aria-hidden="true">{{ $sessions->count() }}</span></div>
            <div>
                <span>Sesiones abiertas</span>
                <strong>{{ $sessions->count() }}</strong>
                <small>En todos tus dispositivos</small>
            </div>
        </article>

        <article class="metric-card metric-card--yellow">
            <div class="metric-card__icon"><span aria-hidden="true">!</span></div>
            <div>
                <span>Alertas últimos 7 días</span>
                <strong>{{ $incidents }}</strong>
                <small>Intentos fallidos o bloqueos</small>
            </div>
        </article>

        <article class="metric-card metric-card--orange">
            <div class="metric-card__icon"><span aria-hidden="true">{{ auth()->user()->is_super_admin ? $activeUsers : '30' }}</span></div>
            <div>
                <span>{{ auth()->user()->is_super_admin ? 'Usuarios activos' : 'Tiempo de sesión' }}</span>
                <strong>{{ auth()->user()->is_super_admin ? $activeUsers : config('security.session_timeout_minutes').' min' }}</strong>
                <small>{{ auth()->user()->is_super_admin ? 'Cuentas con acceso' : 'Límite de inactividad' }}</small>
            </div>
        </article>
    </section>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel__header">
                <div>
                    <span class="panel__eyebrow">Monitoreo</span>
                    <h2>Actividad reciente</h2>
                </div>
                <span class="panel-badge">Últimos registros</span>
            </div>

            <div class="activity-list">
                @forelse ($recentEvents as $event)
                    <article class="activity-item">
                        <span class="activity-dot activity-dot--{{ $event->tone }}" aria-hidden="true"></span>
                        <div class="activity-item__body">
                            <strong>{{ $event->label }}</strong>
                            <span>{{ $event->ip_address ?: 'Dirección no disponible' }}</span>
                        </div>
                        <time datetime="{{ $event->created_at->toIso8601String() }}">
                            {{ $event->created_at->locale('es')->diffForHumans() }}
                        </time>
                    </article>
                @empty
                    <div class="empty-state">
                        <span aria-hidden="true">○</span>
                        <strong>Sin actividad registrada</strong>
                        <p>Los nuevos accesos aparecerán aquí.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="panel security-summary">
            <div class="security-summary__hero">
                <div class="security-lock" style="--security-score: {{ $securityScore }}%" aria-label="Nivel de protección {{ $securityScore }} por ciento">
                    <span class="security-lock__ring" aria-hidden="true"></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7.5 10V7.5a4.5 4.5 0 0 1 9 0V10M6 10h12a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 18 21H6a1.5 1.5 0 0 1-1.5-1.5v-8A1.5 1.5 0 0 1 6 10Z"/>
                        <path d="M12 14v3"/>
                    </svg>
                    <strong>{{ $securityScore }}<small>%</small></strong>
                </div>
                <div>
                    <span class="panel__eyebrow">Estado de protección</span>
                    <h2>Cuenta protegida</h2>
                    <p><strong>{{ $securityControlsPassed }} de {{ $securityControlsTotal }}</strong> controles principales activos.</p>
                </div>
            </div>

            <div class="policy-list">
                <div>
                    <span class="policy-check">✓</span>
                    <p><strong>Bloqueo automático</strong><small>{{ config('security.max_login_attempts') }} intentos permitidos</small></p>
                </div>
                <div>
                    <span class="policy-check">✓</span>
                    <p><strong>Control de inactividad</strong><small>{{ config('security.session_timeout_minutes') }} minutos por sesión</small></p>
                </div>
                <div>
                    <span class="policy-check">✓</span>
                    <p><strong>Contraseña actualizada</strong><small>{{ auth()->user()->password_changed_at?->locale('es')->diffForHumans() ?? 'Sin registro previo' }}</small></p>
                </div>
            </div>

            <a href="{{ route('password.change') }}" class="text-link">Actualizar mi contraseña <span>→</span></a>
        </aside>
    </div>

    <section class="panel sessions-panel">
        <div class="panel__header">
            <div>
                <span class="panel__eyebrow">Control de sesiones</span>
                <h2>Dispositivos con acceso</h2>
            </div>
            <span class="panel-badge panel-badge--secure"><span class="security-pulse"></span> En línea</span>
        </div>

        <div class="session-list">
            @forelse ($sessions as $session)
                <article class="session-item">
                    <span class="device-icon" aria-hidden="true"></span>
                    <div class="session-item__main">
                        <strong>{{ str($session->user_agent ?: 'Navegador no identificado')->contains('Mobile') ? 'Dispositivo móvil' : 'Navegador de escritorio' }}</strong>
                        <span>{{ str($session->user_agent ?: 'Información no disponible')->limit(72) }}</span>
                    </div>
                    <div class="session-item__meta">
                        <strong>{{ $session->ip_address ?: 'IP no disponible' }}</strong>
                        <span>Actividad {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->locale('es')->diffForHumans() }}</span>
                    </div>
                    @if ($session->id === session()->getId())
                        <span class="status-badge status-badge--active">Sesión actual</span>
                    @else
                        <form action="{{ route('sessions.destroy', $session->id) }}" method="POST" data-confirm="¿Finalizar esta sesión?">
                            @csrf
                            @method('DELETE')
                            <button class="button button--danger-soft button--small" type="submit">Finalizar</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state empty-state--compact">
                    <strong>La sesión se está sincronizando</strong>
                    <p>Actualiza la página para consultar el dispositivo activo.</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
