@if($attachments->isNotEmpty())
    <section class="panel ticket-side-card" id="ticket-files"><div class="panel__header"><div><span class="panel__eyebrow">Adjuntos privados</span><h2>Archivos</h2></div><span class="count-pill">{{ $attachments->count() }}</span></div>
        <div class="ticket-files">@foreach($attachments as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}" data-attachment-preview="{{ route('tickets.attachments.preview', [$ticket, $attachment]) }}" data-attachment-name="{{ $attachment->original_name }}" aria-haspopup="dialog"><span>▣</span><div><strong>{{ $attachment->original_name }}</strong><small>{{ $attachment->human_size }} · {{ $attachment->uploadedBy->name }} · Ver archivo</small></div>@if($attachment->is_internal)<em>Interno</em>@endif</a>@endforeach</div>
    </section>
@endif
