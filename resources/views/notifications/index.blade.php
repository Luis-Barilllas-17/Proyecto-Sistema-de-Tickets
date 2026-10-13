@extends('layouts.app')

@section('title', 'Notificaciones')
@section('section-title', 'Centro de notificaciones')

@section('content')
    <header class="notification-heading">
        <div><span class="eyebrow">RF-067 · RF-068</span><h1>Centro de notificaciones</h1><p>Asignaciones, comentarios, decisiones, cambios de estado y alertas operativas en un solo lugar.</p></div>
        @if($counts['unread'])<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="button button--outline">Marcar todo como leído</button></form>@endif
    </header>

    <section class="notification-summary">
        <a href="{{ route('notifications.index') }}" class="{{ $scope==='all'?'is-active':'' }}"><span>Todas</span><strong>{{ $counts['all'] }}</strong></a>
        <a href="{{ route('notifications.index',['scope'=>'unread']) }}" class="{{ $scope==='unread'?'is-active':'' }}"><span>Sin leer</span><strong>{{ $counts['unread'] }}</strong></a>
        <a href="{{ route('notifications.index',['scope'=>'alerts']) }}" class="{{ $scope==='alerts'?'is-active':'' }}"><span>Alertas</span><strong>{{ $counts['alerts'] }}</strong></a>
    </section>

    <section class="panel notification-panel">
        <div class="notification-list">
            @forelse($notifications as $notification)
                @php($data=$notification->data)
                <form method="POST" action="{{ route('notifications.read',$notification->id) }}" class="notification-item is-{{ $data['severity']??'info' }} {{ $notification->read_at?'is-read':'is-unread' }}">@csrf @method('PATCH')
                    <button><span class="notification-item__icon">{{ ($data['severity']??'info')==='critical'?'!':(($data['severity']??'info')==='warning'?'◷':'•') }}</span><div><span>{{ str($data['category']??'sistema')->headline() }}@if(!$notification->read_at)<i>Nueva</i>@endif</span><strong>{{ $data['title']??'Notificación' }}</strong><p>{{ $data['message']??'' }}</p>@if($data['ticket_number']??null)<small>{{ $data['ticket_number'] }}</small>@endif</div><time>{{ $notification->created_at->diffForHumans() }}<small>{{ $notification->created_at->format('d/m/Y H:i') }}</small></time><b>→</b></button>
                </form>
            @empty
                <div class="empty-state"><strong>No hay notificaciones en esta vista</strong><p>Los eventos y alertas de tu alcance aparecerán aquí.</p></div>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $notifications->links() }}</div>
    </section>
@endsection
