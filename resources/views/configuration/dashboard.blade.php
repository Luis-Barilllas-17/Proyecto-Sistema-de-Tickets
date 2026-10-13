@extends('layouts.app')

@section('title', 'Parametrización')
@section('section-title', 'Parametrización, formularios y reglas')

@section('content')
    <section class="module-hero module-hero--configuration">
        <div class="module-hero__content">
            <span class="eyebrow eyebrow--light">Gobierno del servicio</span>
            <h1>El sistema se adapta<br>sin cambiar el código.</h1>
            <p>Configura clasificaciones, formularios, aprobaciones y tiempos de atención desde un entorno central y controlado.</p>
            <div class="module-hero__actions">
                @if (auth()->user()->hasPermission('forms.manage'))
                    <a href="{{ route('configuration.forms.create') }}" class="button button--light">Crear formulario <span>→</span></a>
                @endif
                <a href="{{ route('configuration.service-levels.index') }}" class="button button--glass">Revisar SLA</a>
            </div>
        </div>
        <div class="module-hero__summary">
            <span>Configuración activa</span>
            <strong>{{ $metrics['categories'] + $metrics['forms'] + $metrics['workflows'] }}</strong>
            <small>elementos operativos principales</small>
        </div>
    </section>

    <section class="metric-grid metric-grid--module-three" aria-label="Indicadores de configuración">
        <article class="metric-card metric-card--blue"><div class="metric-card__icon"><span>{{ $metrics['categories'] }}</span></div><div><span>Categorías activas</span><strong>{{ $metrics['categories'] }}</strong><small>Solicitudes, incidentes y desarrollo</small></div></article>
        <article class="metric-card metric-card--teal"><div class="metric-card__icon"><span>{{ $metrics['forms'] }}</span></div><div><span>Formularios publicados</span><strong>{{ $metrics['forms'] }}</strong><small>Campos configurables por clasificación</small></div></article>
        <article class="metric-card metric-card--yellow"><div class="metric-card__icon"><span>{{ $metrics['workflows'] }}</span></div><div><span>Flujos de aprobación</span><strong>{{ $metrics['workflows'] }}</strong><small>{{ $activeDelegations }} suplencias vigentes</small></div></article>
        <article class="metric-card metric-card--orange"><div class="metric-card__icon"><span>{{ $slaCount }}</span></div><div><span>Objetivos SLA</span><strong>{{ $slaCount }}</strong><small>{{ $metrics['priorities'] }} prioridades configuradas</small></div></article>
    </section>

    <section class="module-navigation module-navigation--five" aria-label="Opciones de configuración">
        <a href="{{ route('configuration.categories.index') }}" class="module-link-card"><span class="module-link-card__number">01</span><div><strong>Categorías</strong><p>Clasificación jerárquica y activación de tipos de ticket.</p></div><span class="module-link-card__arrow">→</span></a>
        <a href="{{ route('configuration.forms.index') }}" class="module-link-card"><span class="module-link-card__number">02</span><div><strong>Formularios</strong><p>Diseño de campos, opciones, visibilidad y obligatoriedad.</p></div><span class="module-link-card__arrow">→</span></a>
        <a href="{{ route('configuration.approvals.index') }}" class="module-link-card"><span class="module-link-card__number">03</span><div><strong>Aprobaciones</strong><p>Reglas multinivel y suplencias temporales.</p></div><span class="module-link-card__arrow">→</span></a>
        <a href="{{ route('configuration.routing.index') }}" class="module-link-card"><span class="module-link-card__number">04</span><div><strong>Direccionamiento</strong><p>Área, grupo o responsable para cada clasificación.</p></div><span class="module-link-card__arrow">→</span></a>
        <a href="{{ route('configuration.service-levels.index') }}" class="module-link-card"><span class="module-link-card__number">05</span><div><strong>Estados y SLA</strong><p>Prioridad calculada y tiempos objetivo de atención.</p></div><span class="module-link-card__arrow">→</span></a>
    </section>

    <div class="dashboard-grid configuration-overview-grid">
        <section class="panel">
            <div class="panel__header"><div><span class="panel__eyebrow">Diseñador</span><h2>Formularios recientes</h2></div><a href="{{ route('configuration.forms.index') }}" class="text-link">Ver todos <span>→</span></a></div>
            <div class="configuration-list">
                @forelse ($forms as $form)
                    <article><span class="config-code">{{ $form->code }}</span><div><strong>{{ $form->name }}</strong><span>{{ $form->category->name }}{{ $form->subcategory ? ' · '.$form->subcategory->name : '' }}</span></div><span class="count-pill">{{ $form->fields_count }} campos</span></article>
                @empty
                    <div class="empty-state"><strong>Aún no hay formularios</strong><p>Crea el primero para definir la información de los tickets.</p></div>
                @endforelse
            </div>
        </section>

        <aside class="panel">
            <div class="panel__header"><div><span class="panel__eyebrow">Control previo</span><h2>Flujos activos</h2></div></div>
            <div class="workflow-mini-list">
                @forelse ($workflows as $workflow)
                    <article><span class="workflow-mini-list__step">{{ $workflow->levels_count }}</span><div><strong>{{ $workflow->name }}</strong><span>{{ $workflow->category->name }} · {{ $workflow->levels_count }} niveles</span></div></article>
                @empty
                    <div class="empty-state"><strong>Sin reglas de aprobación</strong><p>Los tickets avanzarán sin una aprobación previa.</p></div>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
