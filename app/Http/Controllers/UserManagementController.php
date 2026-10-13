<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Department;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['roles', 'area', 'department'])
            ->withCount('technicalGroups')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('area'), fn ($query) => $query->where('area_id', $request->integer('area')))
            ->when($request->filled('role'), function ($query) use ($request): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('roles.id', $request->integer('role')));
            })
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('administration.users.index', [
            'users' => $users,
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('administration.users.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        $user = DB::transaction(function () use ($request, $validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'employee_code' => $validated['employee_code'] ?? null,
                'email' => Str::lower($validated['email']),
                'job_title' => $validated['job_title'] ?? null,
                'phone_extension' => $validated['phone_extension'] ?? null,
                'area_id' => $validated['area_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'force_password_change' => true,
            ]);

            $user->roles()->sync($validated['role_ids']);
            $user->specialties()->sync($validated['specialty_ids'] ?? []);
            $this->syncSuperAdminFlag($user);

            SecurityEvent::record('user_created', $request, $user, [
                'performed_by' => $request->user()->id,
            ]);

            return $user;
        });

        return redirect()->route('administration.users.index')
            ->with('success', 'Usuario '.$user->name.' creado. Deberá cambiar la contraseña temporal al ingresar.');
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'specialties']);

        return view('administration.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $superRoleId = Role::where('slug', 'super-administrador')->value('id');

        if ($user->is($request->user()) && ($validated['status'] !== 'active' || ! in_array($superRoleId, $validated['role_ids']))) {
            return back()->withInput()->withErrors([
                'status' => 'No puedes desactivar tu cuenta ni retirar tu propio perfil de Super Administrador.',
            ]);
        }

        DB::transaction(function () use ($request, $validated, $user): void {
            $user->forceFill([
                'name' => $validated['name'],
                'employee_code' => $validated['employee_code'] ?? null,
                'email' => Str::lower($validated['email']),
                'job_title' => $validated['job_title'] ?? null,
                'phone_extension' => $validated['phone_extension'] ?? null,
                'area_id' => $validated['area_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'status' => $validated['status'],
            ])->save();

            $user->roles()->sync($validated['role_ids']);
            $user->specialties()->sync($validated['specialty_ids'] ?? []);
            $this->syncSuperAdminFlag($user);

            if ($validated['status'] !== 'active') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            SecurityEvent::record('user_updated', $request, $user, [
                'performed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('administration.users.index')->with('success', 'Información del usuario actualizada.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRules = $user
            ? ['nullable', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()->symbols()]
            : ['required', 'confirmed', Rules\Password::min(10)->mixedCase()->numbers()->symbols()];

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone_extension' => ['nullable', 'string', 'max:20'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'password' => $passwordRules,
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'specialty_ids' => ['nullable', 'array'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
        ], [
            'role_ids.required' => 'Asigna al menos un perfil al usuario.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $email = Str::lower($validated['email']);
        $domains = config('security.institutional_domains', []);
        if ($domains !== [] && ! Str::endsWith($email, array_map(fn ($domain) => '@'.$domain, $domains))) {
            throw ValidationException::withMessages(['email' => 'Utiliza un correo institucional autorizado.']);
        }

        if (! empty($validated['department_id'])) {
            $belongsToArea = Department::whereKey($validated['department_id'])
                ->where('area_id', $validated['area_id'] ?? 0)
                ->exists();
            if (! $belongsToArea) {
                throw ValidationException::withMessages([
                    'department_id' => 'El departamento seleccionado no pertenece al área indicada.',
                ]);
            }
        }

        return $validated;
    }

    private function formData(?User $user = null): array
    {
        return [
            'user' => $user,
            'roles' => Role::where('is_active', true)->orderBy('name')->get(),
            'areas' => Area::with(['departments' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'specialties' => Specialty::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
        ];
    }

    private function syncSuperAdminFlag(User $user): void
    {
        $isSuperAdmin = $user->roles()->where('slug', 'super-administrador')->exists();
        $user->forceFill(['is_super_admin' => $isSuperAdmin])->save();
    }
}
