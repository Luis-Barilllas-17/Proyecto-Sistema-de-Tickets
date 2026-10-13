@extends('layouts.app')

@php
    $editing = isset($user) && $user;
    $selectedRoles = collect(old('role_ids', $editing ? $user->roles->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $selectedSpecialties = collect(old('specialty_ids', $editing ? $user->specialties->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

@section('title', $editing ? 'Editar usuario' : 'Nuevo usuario')
@section('section-title', 'Usuarios institucionales')

@section('content')
    <div class="page-heading page-heading--compact">
        <div>
            <a href="{{ route('administration.users.index') }}" class="back-link"><span>←</span> Volver al directorio</a>
            <span class="eyebrow">{{ $editing ? 'Actualización de cuenta' : 'Registro institucional' }}</span>
            <h1>{{ $editing ? 'Editar usuario' : 'Crear usuario' }}</h1>
            <p>{{ $editing ? 'Actualiza la información, ubicación y perfiles autorizados.' : 'Registra una cuenta con correo institucional, perfil y ubicación organizacional.' }}</p>
        </div>
    </div>

    <form action="{{ $editing ? route('administration.users.update', $user) : route('administration.users.store') }}" method="POST" class="entity-form-layout">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="entity-form-main">
            <section class="panel entity-section">
                <div class="entity-section__header"><span class="section-number">01</span><div><h2>Identidad institucional</h2><p>Información principal de la persona y su cuenta.</p></div></div>
                <div class="form-grid form-grid--two">
                    <div class="field field--wide"><label for="name">Nombre completo <strong>*</strong></label><div class="input-shell"><input id="name" name="name" value="{{ old('name', $user?->name) }}" maxlength="255" required></div></div>
                    <div class="field"><label for="employee_code">Código de empleado</label><div class="input-shell"><input id="employee_code" name="employee_code" value="{{ old('employee_code', $user?->employee_code) }}" placeholder="Ej. IT-025" maxlength="30"></div></div>
                    <div class="field"><label for="email">Correo institucional <strong>*</strong></label><div class="input-shell"><span class="input-prefix">@</span><input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" placeholder="nombre@zolicguate.com" required></div></div>
                    <div class="field"><label for="job_title">Puesto o cargo</label><div class="input-shell"><input id="job_title" name="job_title" value="{{ old('job_title', $user?->job_title) }}" placeholder="Ej. Técnico de soporte"></div></div>
                    <div class="field"><label for="phone_extension">Extensión telefónica</label><div class="input-shell"><input id="phone_extension" name="phone_extension" value="{{ old('phone_extension', $user?->phone_extension) }}" placeholder="Ej. 214" maxlength="20"></div></div>
                </div>
            </section>

            <section class="panel entity-section">
                <div class="entity-section__header"><span class="section-number">02</span><div><h2>Ubicación organizacional</h2><p>Área, departamento y estado operativo de la cuenta.</p></div></div>
                <div class="form-grid form-grid--three">
                    <div class="field"><label for="area_id">Área</label><div class="select-shell"><select id="area_id" name="area_id" data-area-select><option value="">Seleccionar área</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) old('area_id', $user?->area_id) === (string) $area->id)>{{ $area->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="department_id">Departamento</label><div class="select-shell"><select id="department_id" name="department_id" data-department-select><option value="">Sin departamento</option>@foreach ($areas as $area)@foreach ($area->departments as $department)<option value="{{ $department->id }}" data-area="{{ $area->id }}" @selected((string) old('department_id', $user?->department_id) === (string) $department->id)>{{ $department->name }}</option>@endforeach @endforeach</select></div></div>
                    <div class="field"><label for="status">Estado de cuenta <strong>*</strong></label><div class="select-shell"><select id="status" name="status" required><option value="active" @selected(old('status', $user?->status ?? 'active') === 'active')>Activa</option><option value="suspended" @selected(old('status', $user?->status) === 'suspended')>Suspendida</option><option value="inactive" @selected(old('status', $user?->status) === 'inactive')>Inactiva</option></select></div></div>
                </div>
            </section>

            <section class="panel entity-section">
                <div class="entity-section__header"><span class="section-number">03</span><div><h2>Perfiles y permisos</h2><p>Asigna uno o varios perfiles según sus responsabilidades.</p></div></div>
                <div class="role-selection-grid">
                    @foreach ($roles as $role)
                        <label class="selection-card">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($selectedRoles->contains($role->id))>
                            <span class="selection-card__check">✓</span>
                            <span><strong>{{ $role->name }}</strong><small>{{ $role->description }}</small></span>
                        </label>
                    @endforeach
                </div>
            </section>

            @if ($specialties->isNotEmpty())
                <section class="panel entity-section">
                    <div class="entity-section__header"><span class="section-number">04</span><div><h2>Especialidades</h2><p>Opcional para perfiles técnicos y de desarrollo.</p></div></div>
                    <div class="specialty-selection">
                        @foreach ($specialties->groupBy('type') as $type => $items)
                            <div><span class="selection-group-title">{{ $type === 'programming' ? 'Desarrollo' : 'Técnicas' }}</span><div class="chip-checkboxes">@foreach ($items as $specialty)<label><input type="checkbox" name="specialty_ids[]" value="{{ $specialty->id }}" @checked($selectedSpecialties->contains($specialty->id))><span>{{ $specialty->name }}</span></label>@endforeach</div></div>
                        @endforeach
                    </div>
                </section>
            @endif

            @unless ($editing)
                <section class="panel entity-section">
                    <div class="entity-section__header"><span class="section-number">05</span><div><h2>Acceso temporal</h2><p>El usuario deberá crear una contraseña personal en su primer ingreso.</p></div></div>
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="password">Contraseña temporal <strong>*</strong></label><div class="input-shell"><span class="input-prefix input-prefix--lock"></span><input id="password" name="password" type="password" autocomplete="new-password" required data-password-input><button type="button" class="password-toggle" data-password-toggle><span>Ver</span></button></div><span class="field__hint">10 caracteres, mayúscula, minúscula, número y símbolo.</span></div>
                        <div class="field"><label for="password_confirmation">Confirmar contraseña <strong>*</strong></label><div class="input-shell"><span class="input-prefix input-prefix--lock"></span><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-password-input><button type="button" class="password-toggle" data-password-toggle><span>Ver</span></button></div></div>
                    </div>
                </section>
            @endunless
        </div>

        <aside class="entity-form-sidebar">
            <section class="panel form-summary-card">
                <img src="{{ asset('images/brand/logo-zolic-blue.png') }}" alt="ZOLIC" class="form-summary-card__logo">
                <span class="panel__eyebrow">{{ $editing ? 'Cuenta seleccionada' : 'Nueva cuenta' }}</span>
                <h2>{{ $editing ? $user->name : 'Usuario institucional' }}</h2>
                <p>{{ $editing ? $user->email : 'Completa los campos obligatorios para registrar el acceso.' }}</p>
                <div class="form-summary-list"><span><i></i> Correo institucional validado</span><span><i></i> Permisos definidos por perfil</span><span><i></i> Actividad incluida en bitácora</span></div>
            </section>

            @if ($editing && auth()->user()->hasPermission('users.reset_password'))
                <a href="{{ route('security.password.edit', $user) }}" class="sidebar-action-card"><span>Acceso</span><strong>Restablecer contraseña</strong><small>Cierra sesiones y obliga a crear una clave personal.</small></a>
            @endif

            <div class="sticky-form-actions">
                <a href="{{ route('administration.users.index') }}" class="button button--ghost">Cancelar</a>
                <button type="submit" class="button button--primary">{{ $editing ? 'Guardar cambios' : 'Crear usuario' }} <span>→</span></button>
            </div>
        </aside>
    </form>
@endsection
