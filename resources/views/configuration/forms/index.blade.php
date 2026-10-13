@extends('layouts.app')

@section('title', 'Diseñador de formularios')
@section('section-title', 'Formularios configurables')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Diseño configurable</span><h1>Diseñador de formularios</h1><p>Crea formularios por clasificación y define campos obligatorios, opcionales, visibles o de solo lectura.</p></div>
        @if(auth()->user()->hasPermission('forms.manage'))<a href="{{ route('configuration.forms.create') }}" class="button button--primary">Nuevo formulario <span>→</span></a>@endif
    </div>

    <section class="form-catalog-grid">
        @forelse($forms as $form)
            <article class="panel form-catalog-card">
                <header><span class="config-code">{{ $form->code }}</span><span class="status-badge status-badge--{{ $form->is_active ? 'active' : 'inactive' }}">{{ $form->is_active ? 'Publicado' : 'Inactivo' }}</span></header>
                <div class="form-catalog-card__classification"><span>{{ $form->category->name }}</span><i>→</i><span>{{ $form->subcategory?->name ?? 'Todas las subcategorías' }}</span></div>
                <h2>{{ $form->name }}</h2><p>{{ $form->description }}</p>
                <div class="form-catalog-card__meta"><span><strong>{{ $form->fields_count }}</strong> campos</span><span>Versión <strong>{{ $form->version }}</strong></span></div>
                <footer>@if(auth()->user()->hasPermission('forms.manage'))<a href="{{ route('configuration.forms.edit', $form) }}" class="button button--outline button--small">Configurar campos</a>@endif<span>Actualizado {{ $form->updated_at->diffForHumans() }}</span></footer>
            </article>
        @empty
            <div class="panel empty-state empty-state--wide"><strong>No hay formularios configurados</strong><p>Crea un formulario y asócialo con una categoría o subcategoría.</p></div>
        @endforelse
    </section>
@endsection
