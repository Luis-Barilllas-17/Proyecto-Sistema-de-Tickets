<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('administration.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
            'permissionCount' => Permission::count(),
        ]);
    }

    public function create(): View
    {
        return view('administration.roles.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);
        $slug = Str::slug($validated['name']);

        if (Role::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un rol con un nombre equivalente.']);
        }

        $role = DB::transaction(function () use ($request, $validated, $slug): Role {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_system' => false,
                'is_active' => $request->boolean('is_active', true),
            ]);
            $role->permissions()->sync($validated['permission_ids'] ?? []);
            SecurityEvent::record('role_created', $request, details: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'performed_by' => $request->user()->id,
            ]);

            return $role;
        });

        return redirect()->route('administration.roles.index')->with('success', 'Rol '.$role->name.' creado correctamente.');
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('administration.roles.form', $this->formData($role));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validateRole($request, $role);

        if ($role->slug === 'super-administrador' && ! $request->boolean('is_active', true)) {
            return back()->withInput()->withErrors(['is_active' => 'El perfil de Super Administrador no puede desactivarse.']);
        }

        $slug = $role->is_system ? $role->slug : Str::slug($validated['name']);
        if (Role::where('slug', $slug)->whereKeyNot($role->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un rol con un nombre equivalente.']);
        }

        DB::transaction(function () use ($request, $validated, $role, $slug): void {
            $role->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
            $role->permissions()->sync($validated['permission_ids'] ?? []);
            SecurityEvent::record('role_updated', $request, details: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'performed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('administration.roles.index')->with('success', 'Rol y permisos actualizados.');
    }

    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('roles')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
    }

    private function formData(?Role $role = null): array
    {
        return [
            'role' => $role,
            'permissionGroups' => Permission::orderBy('module')->orderBy('action')->get()->groupBy('module'),
        ];
    }
}
