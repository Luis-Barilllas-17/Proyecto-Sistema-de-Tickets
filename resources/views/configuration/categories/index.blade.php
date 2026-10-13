@extends('layouts.app')

@section('title', 'Categorías y subcategorías')
@section('section-title', 'Clasificación de tickets')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Clasificación del servicio</span><h1>Categorías y subcategorías</h1><p>Organiza los tipos de solicitud, incidente y desarrollo que estarán disponibles para los usuarios.</p></div>
        <div class="page-heading__meta"><span class="security-pulse"></span><div><strong>{{ $categories->where('is_active', true)->count() }} categorías activas</strong><span>{{ $categories->sum(fn ($category) => $category->subcategories->count()) }} subcategorías registradas</span></div></div>
    </div>

    @if (auth()->user()->hasPermission('categories.manage'))
        <div class="organization-forms">
            <section class="panel quick-entity-form {{ $editingCategory ? 'is-editing' : '' }}">
                <div class="quick-entity-form__header"><span class="section-number">C</span><div><span class="panel__eyebrow">{{ $editingCategory ? 'Edición' : 'Nuevo registro' }}</span><h2>{{ $editingCategory ? 'Editar categoría' : 'Registrar categoría' }}</h2></div>@if($editingCategory)<a href="{{ route('configuration.categories.index') }}">Cancelar</a>@endif</div>
                <form action="{{ $editingCategory ? route('configuration.categories.update', $editingCategory) : route('configuration.categories.store') }}" method="POST" class="stack-form stack-form--compact">
                    @csrf @if($editingCategory) @method('PUT') @endif
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="category-code">Código <strong>*</strong></label><div class="input-shell"><input id="category-code" name="code" value="{{ old('code', $editingCategory?->code) }}" maxlength="30" placeholder="Ej. SOP" required></div></div>
                        <div class="field"><label for="category-type">Tipo <strong>*</strong></label><div class="select-shell"><select id="category-type" name="type" required>@foreach($categoryTypes as $value => $label)<option value="{{ $value }}" @selected(old('type', $editingCategory?->type ?? 'request') === $value)>{{ $label }}</option>@endforeach</select></div></div>
                        <div class="field field--wide"><label for="category-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="category-name" name="name" value="{{ old('name', $editingCategory?->name) }}" maxlength="160" required></div></div>
                        <div class="field"><label for="category-order">Orden <strong>*</strong></label><div class="input-shell"><input id="category-order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $editingCategory?->sort_order ?? 0) }}" required></div></div>
                        <div class="field field--wide"><label for="category-description">Descripción</label><div class="textarea-shell"><textarea id="category-description" name="description" rows="2" maxlength="1500">{{ old('description', $editingCategory?->description) }}</textarea></div></div>
                    </div>
                    <div class="quick-form-footer"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $editingCategory?->is_active ?? true))><span></span><div><strong>Categoría activa</strong></div></label><button class="button button--primary button--small" type="submit">{{ $editingCategory ? 'Guardar categoría' : 'Registrar categoría' }}</button></div>
                </form>
            </section>

            <section class="panel quick-entity-form {{ $editingSubcategory ? 'is-editing' : '' }}">
                <div class="quick-entity-form__header"><span class="section-number">S</span><div><span class="panel__eyebrow">{{ $editingSubcategory ? 'Edición' : 'Nuevo registro' }}</span><h2>{{ $editingSubcategory ? 'Editar subcategoría' : 'Registrar subcategoría' }}</h2></div>@if($editingSubcategory)<a href="{{ route('configuration.categories.index') }}">Cancelar</a>@endif</div>
                <form action="{{ $editingSubcategory ? route('configuration.subcategories.update', $editingSubcategory) : route('configuration.subcategories.store') }}" method="POST" class="stack-form stack-form--compact">
                    @csrf @if($editingSubcategory) @method('PUT') @endif
                    <div class="form-grid form-grid--two">
                        <div class="field field--wide"><label for="subcategory-category">Categoría <strong>*</strong></label><div class="select-shell"><select id="subcategory-category" name="ticket_category_id" required><option value="">Seleccionar categoría</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('ticket_category_id', $editingSubcategory?->ticket_category_id) === (string)$category->id)>{{ $category->code }} · {{ $category->name }}</option>@endforeach</select></div></div>
                        <div class="field"><label for="subcategory-code">Código <strong>*</strong></label><div class="input-shell"><input id="subcategory-code" name="code" value="{{ old('code', $editingSubcategory?->code) }}" maxlength="30" placeholder="Ej. HWD" required></div></div>
                        <div class="field"><label for="subcategory-order">Orden <strong>*</strong></label><div class="input-shell"><input id="subcategory-order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $editingSubcategory?->sort_order ?? 0) }}" required></div></div>
                        <div class="field field--wide"><label for="subcategory-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="subcategory-name" name="name" value="{{ old('name', $editingSubcategory?->name) }}" maxlength="160" required></div></div>
                        <div class="field field--wide"><label for="subcategory-description">Descripción</label><div class="textarea-shell"><textarea id="subcategory-description" name="description" rows="2" maxlength="1500">{{ old('description', $editingSubcategory?->description) }}</textarea></div></div>
                    </div>
                    <div class="quick-form-footer"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $editingSubcategory?->is_active ?? true))><span></span><div><strong>Subcategoría activa</strong></div></label><button class="button button--primary button--small" type="submit">{{ $editingSubcategory ? 'Guardar subcategoría' : 'Registrar subcategoría' }}</button></div>
                </form>
            </section>
        </div>
    @endif

    <section class="category-config-grid">
        @forelse($categories as $category)
            <article class="panel category-config-card">
                <header><div><span class="config-code">{{ $category->code }}</span><span class="type-badge type-badge--{{ $category->type === 'incident' ? 'it' : 'organizational' }}">{{ $categoryTypes[$category->type] ?? $category->type }}</span></div><span class="status-badge status-badge--{{ $category->is_active ? 'active' : 'inactive' }}">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</span></header>
                <h2>{{ $category->name }}</h2><p>{{ $category->description }}</p>
                <div class="category-config-card__stats"><span><strong>{{ $category->subcategories->count() }}</strong> subcategorías</span><span><strong>{{ $category->forms_count }}</strong> formularios</span><span><strong>{{ $category->routing_rules_count }}</strong> rutas</span></div>
                <div class="subcategory-list">
                    @forelse($category->subcategories as $subcategory)
                        <div><span><strong>{{ $subcategory->code }}</strong>{{ $subcategory->name }}</span><div><span class="status-dot {{ $subcategory->is_active ? 'is-active' : '' }}"></span>@if(auth()->user()->hasPermission('categories.manage'))<a href="{{ route('configuration.categories.index', ['edit_subcategory' => $subcategory->id]) }}">Editar</a>@endif</div></div>
                    @empty
                        <span class="empty-inline">Sin subcategorías configuradas</span>
                    @endforelse
                </div>
                @if(auth()->user()->hasPermission('categories.manage'))<footer><a href="{{ route('configuration.categories.index', ['edit_category' => $category->id]) }}" class="button button--outline button--small">Editar categoría</a></footer>@endif
            </article>
        @empty
            <div class="panel empty-state"><strong>No hay categorías</strong><p>Registra la primera clasificación para comenzar.</p></div>
        @endforelse
    </section>
@endsection
