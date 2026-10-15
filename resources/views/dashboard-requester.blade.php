<section class="requester-welcome">
    <div class="requester-welcome__content">
        <span class="eyebrow">Portal del solicitante</span>
        <h1>Hola, {{ str(auth()->user()->name)->before(' ') }}</h1>
        <p>Registra nuevas solicitudes y consulta el avance de la atención brindada por el Departamento de IT.</p>
        <div class="requester-welcome__actions">
            <a href="{{ route('tickets.create') }}" class="button button--primary">Crear solicitud <span aria-hidden="true">＋</span></a>
            <a href="{{ route('tickets.index', ['scope' => 'mine']) }}" class="button button--requester-secondary">Consultar mis tickets</a>
        </div>
    </div>
    <aside class="requester-welcome__identity">
        <span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
        <div>
            <small>Usuario solicitante</small>
            <strong>{{ auth()->user()->name }}</strong>
            <span>{{ auth()->user()->area?->name ?? 'Área no asignada' }}</span>
        </div>
    </aside>
</section>

<section class="requester-metrics" aria-label="Resumen de mis solicitudes">
    <article><span>01</span><div><small>Total de solicitudes</small><strong>{{ $requesterMetrics['total'] }}</strong><p>Historial registrado</p></div></article>
    <article class="is-draft"><span>02</span><div><small>Borradores</small><strong>{{ $requesterMetrics['drafts'] }}</strong><p>Pendientes de envío</p></div></article>
    <article class="is-active"><span>03</span><div><small>En atención</small><strong>{{ $requesterMetrics['active'] }}</strong><p>Actualmente gestionadas</p></div></article>
    <article class="is-resolved"><span>04</span><div><small>Solucionadas</small><strong>{{ $requesterMetrics['resolved'] }}</strong><p>Atenciones finalizadas</p></div></article>
</section>

<div class="requester-workspace">
    <section class="panel requester-tickets">
        <header class="panel__header">
            <div><span class="panel__eyebrow">Seguimiento personal</span><h2>Solicitudes recientes</h2></div>
            <a href="{{ route('tickets.index', ['scope' => 'mine']) }}" class="text-link">Ver todas <span aria-hidden="true">→</span></a>
        </header>
        <div class="requester-ticket-list">
            @forelse ($requesterRecentTickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="requester-ticket">
                    <span class="requester-ticket__code">{{ $ticket->number }}</span>
                    <div>
                        <strong>{{ $ticket->title ?: 'Solicitud sin título' }}</strong>
                        <small>{{ $ticket->category?->name ?? 'Sin categoría' }} · Actualizado {{ $ticket->updated_at->locale('es')->diffForHumans() }}</small>
                    </div>
                    <span class="requester-ticket__status" style="--ticket-state: {{ $ticket->status?->color ?? '#225C9A' }}">{{ $ticket->is_draft ? 'Borrador' : ($ticket->status?->name ?? 'Registrado') }}</span>
                    <span class="requester-ticket__arrow" aria-hidden="true">→</span>
                </a>
            @empty
                <div class="empty-state"><strong>Aún no tienes solicitudes</strong><p>Cuando registres un ticket podrás consultar aquí su estado y avance.</p></div>
            @endforelse
        </div>
    </section>

    <aside class="requester-side">
        <section class="panel requester-guide">
            <span class="panel__eyebrow">Atención de IT</span>
            <h2>¿Necesitas asistencia?</h2>
            <p>Describe claramente el incidente o requerimiento para facilitar su clasificación y atención.</p>
            <ol>
                <li><span>1</span>Selecciona la categoría.</li>
                <li><span>2</span>Agrega detalles y evidencias.</li>
                <li><span>3</span>Consulta el seguimiento.</li>
            </ol>
            <a href="{{ route('tickets.create') }}" class="button button--primary button--full">Iniciar una solicitud</a>
        </section>
        <section class="requester-account-note">
            <span aria-hidden="true">✓</span>
            <div><strong>Cuenta institucional activa</strong><p>Tu actividad y solicitudes quedan registradas para seguimiento.</p></div>
        </section>
    </aside>
</div>
