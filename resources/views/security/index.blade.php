@extends('layouts.app')

@section('title', 'Control de acceso')
@section('section-title', 'Control de acceso')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración · Bloque 1</span>
            <h1>Control de acceso</h1>
            <p>Administra estados de cuenta, restablece contraseñas, finaliza sesiones y consulta la bitácora.</p>
        </div>
        <div class="page-heading__meta">
            <span class="security-pulse"></span>
            <div><strong>Políticas activas</strong><span>Protección operativa</span></div>
        </div>
    </div>

    <nav class="section-tabs" aria-label="Secciones de control">
        <a href="#usuarios" class="is-active">Usuarios <span>{{ $users->count() }}</span></a>
        <a href="#sesiones">Sesiones <span>{{ $sessions->count() }}</span></a>
        <a href="#bitacora">Bitácora <span>{{ $events->count() }}</span></a>
    </nav>

    <section class="panel admin-panel" id="usuarios">
        <div class="panel__header">
            <div>
                <span class="panel__eyebrow">Administración de acceso</span>
                <h2>Cuentas institucionales</h2>
            </div>
            <div class="legend">
                <span><i class="legend-dot legend-dot--green"></i> Activa</span>
                <span><i class="legend-dot legend-dot--yellow"></i> Suspendida</span>
                <span><i class="legend-dot legend-dot--gray"></i> Inactiva</span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th>Protección</th>
                        <th class="align-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar avatar--table">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                                    <div>
                                        <strong>{{ $user->name }}</strong>
                                        <span>{{ $user->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-label">{{ $user->is_super_admin ? 'Super Administrador' : 'Usuario institucional' }}</span>
                            </td>
                            <td>
                                @php
                                    $statusLabels = ['active' => 'Activa', 'suspended' => 'Suspendida', 'inactive' => 'Inactiva'];
                                @endphp
                                <span class="status-badge status-badge--{{ $user->status }}">
                                    <i></i>{{ $statusLabels[$user->status] ?? $user->status }}
                                </span>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <strong>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Sin accesos' }}</strong>
                                    <span>{{ $user->last_login_ip ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                @if ($user->isLocked())
                                    <div class="lock-control">
                                        <span class="lock-label lock-label--locked">Bloqueada hasta {{ $user->locked_until->format('H:i') }}</span>
                                        <form action="{{ route('security.users.unlock', $user) }}" method="POST">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="unlock-link">Desbloquear ahora</button>
                                        </form>
                                    </div>
                                @elseif ($user->failed_login_attempts > 0)
                                    <span class="lock-label lock-label--warning">{{ $user->failed_login_attempts }} intento(s) fallido(s)</span>
                                @else
                                    <span class="lock-label">Sin alertas</span>
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('security.password.edit', $user) }}" class="button button--outline button--small">Contraseña</a>
                                    <form action="{{ route('security.users.status', $user) }}" method="POST" class="status-form">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="status-{{ $user->id }}">Estado de {{ $user->name }}</label>
                                        <select id="status-{{ $user->id }}" name="status" {{ $user->is(auth()->user()) ? 'disabled' : '' }}>
                                            <option value="active" @selected($user->status === 'active')>Activar</option>
                                            <option value="suspended" @selected($user->status === 'suspended')>Suspender</option>
                                            <option value="inactive" @selected($user->status === 'inactive')>Desactivar</option>
                                        </select>
                                        @unless ($user->is(auth()->user()))
                                            <button type="submit" class="icon-button" title="Guardar estado" aria-label="Guardar estado">✓</button>
                                        @endunless
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel admin-panel" id="sesiones">
        <div class="panel__header">
            <div>
                <span class="panel__eyebrow">Gestión de sesiones</span>
                <h2>Sesiones activas</h2>
            </div>
            <span class="panel-badge panel-badge--secure"><span class="security-pulse"></span> {{ $sessions->count() }} conectada(s)</span>
        </div>

        <div class="session-list">
            @forelse ($sessions as $session)
                <article class="session-item">
                    <span class="device-icon" aria-hidden="true"></span>
                    <div class="session-item__main">
                        <strong>{{ $session->name ?? 'Usuario no disponible' }}</strong>
                        <span>{{ $session->email ?? '—' }} · {{ str($session->user_agent ?: 'Navegador no identificado')->limit(58) }}</span>
                    </div>
                    <div class="session-item__meta">
                        <strong>{{ $session->ip_address ?: 'IP no disponible' }}</strong>
                        <span>{{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->locale('es')->diffForHumans() }}</span>
                    </div>
                    @if ($session->id === session()->getId())
                        <span class="status-badge status-badge--active">Tu sesión</span>
                    @else
                        <form action="{{ route('security.sessions.destroy', $session->id) }}" method="POST" data-confirm="¿Finalizar la sesión de este usuario?">
                            @csrf
                            @method('DELETE')
                            <button class="button button--danger-soft button--small" type="submit">Finalizar</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="empty-state empty-state--compact">
                    <strong>No hay sesiones activas registradas</strong>
                    <p>Las nuevas conexiones aparecerán en este apartado.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="panel admin-panel" id="bitacora">
        <div class="panel__header">
            <div>
                <span class="panel__eyebrow">Registro de accesos</span>
                <h2>Bitácora de seguridad</h2>
            </div>
            <span class="panel-badge">50 eventos recientes</span>
        </div>

        <div class="table-wrap">
            <table class="data-table data-table--events">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Usuario / correo</th>
                        <th>Dirección IP</th>
                        <th>Fecha y hora</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>
                                <div class="event-cell">
                                    <span class="activity-dot activity-dot--{{ $event->tone }}"></span>
                                    <strong>{{ $event->label }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="date-cell">
                                    <strong>{{ $event->user?->name ?? 'Usuario no identificado' }}</strong>
                                    <span>{{ $event->email ?? '—' }}</span>
                                </div>
                            </td>
                            <td><span class="ip-label">{{ $event->ip_address ?? '—' }}</span></td>
                            <td>
                                <div class="date-cell">
                                    <strong>{{ $event->created_at->format('d/m/Y') }}</strong>
                                    <span>{{ $event->created_at->format('H:i:s') }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><strong>Sin eventos registrados</strong></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
