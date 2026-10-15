<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\SecurityEvent;
use App\Models\Specialty;
use App\Models\TechnicalGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TechnicalGroupController extends Controller
{
    public function index(Request $request): View
    {
        $groups = TechnicalGroup::with(['area', 'coordinator', 'members.roles'])
            ->withCount('members')
            ->orderBy('name')
            ->get();
        $specialties = Specialty::withCount('users')->orderBy('type')->orderBy('name')->get();

        return view('administration.groups.index', [
            'groups' => $groups,
            'specialties' => $specialties,
            'editingSpecialty' => $request->integer('edit_specialty') ? Specialty::findOrFail($request->integer('edit_specialty')) : null,
            'metrics' => [
                'active_groups' => $groups->where('is_active', true)->count(),
                'members' => $groups->flatMap->members->unique('id')->count(),
                'areas' => $groups->pluck('area_id')->filter()->unique()->count(),
                'specialties' => $specialties->where('is_active', true)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('administration.groups.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGroup($request);

        $group = DB::transaction(function () use ($request, $validated): TechnicalGroup {
            $group = TechnicalGroup::create($this->groupPayload($request, $validated));
            $group->members()->sync($this->memberIds($validated));
            SecurityEvent::record('group_created', $request, details: [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'performed_by' => $request->user()->id,
            ]);

            return $group;
        });

        return redirect()->route('administration.groups.index')->with('success', 'Grupo '.$group->name.' creado.');
    }

    public function edit(TechnicalGroup $group): View
    {
        $group->load('members');

        return view('administration.groups.form', $this->formData($group));
    }

    public function update(Request $request, TechnicalGroup $group): RedirectResponse
    {
        $validated = $this->validateGroup($request, $group);

        DB::transaction(function () use ($request, $validated, $group): void {
            $group->update($this->groupPayload($request, $validated));
            $group->members()->sync($this->memberIds($validated));
            SecurityEvent::record('group_updated', $request, details: [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'performed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('administration.groups.index')->with('success', 'Grupo y miembros actualizados.');
    }

    public function storeSpecialty(Request $request): RedirectResponse
    {
        $validated = $this->validateSpecialty($request);
        $specialty = Specialty::create($this->specialtyPayload($request, $validated));
        SecurityEvent::record('specialty_created', $request, details: [
            'specialty_id' => $specialty->id,
            'specialty_name' => $specialty->name,
            'performed_by' => $request->user()->id,
        ]);

        return redirect(route('administration.groups.index').'#especialidades')->with('success', 'Especialidad registrada.');
    }

    public function updateSpecialty(Request $request, Specialty $specialty): RedirectResponse
    {
        $validated = $this->validateSpecialty($request, $specialty);
        $specialty->update($this->specialtyPayload($request, $validated));
        SecurityEvent::record('specialty_updated', $request, details: [
            'specialty_id' => $specialty->id,
            'specialty_name' => $specialty->name,
            'performed_by' => $request->user()->id,
        ]);

        return redirect(route('administration.groups.index').'#especialidades')->with('success', 'Especialidad actualizada.');
    }

    private function validateGroup(Request $request, ?TechnicalGroup $group = null): array
    {
        return $request->validate([
            'area_id' => ['required', 'exists:areas,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('it_groups')->ignore($group?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'coordinator_user_id' => ['nullable', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function groupPayload(Request $request, array $validated): array
    {
        return [
            'area_id' => $validated['area_id'],
            'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'coordinator_user_id' => $validated['coordinator_user_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function memberIds(array $validated): array
    {
        $ids = $validated['member_ids'] ?? [];
        if (! empty($validated['coordinator_user_id'])) {
            $ids[] = (int) $validated['coordinator_user_id'];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function validateSpecialty(Request $request, ?Specialty $specialty = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('specialties')->ignore($specialty?->id)],
            'type' => ['required', Rule::in(['technical', 'programming'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function specialtyPayload(Request $request, array $validated): array
    {
        return [
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function formData(?TechnicalGroup $group = null): array
    {
        return [
            'group' => $group,
            'itAreas' => Area::where('type', 'it')->where('is_active', true)->orderBy('name')->get(),
            'candidates' => User::with(['roles', 'specialties'])
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('slug', [
                    'jefatura-it', 'encargado-area-it', 'tecnico-it', 'programador-it',
                ]))
                ->orderBy('name')
                ->get(),
        ];
    }
}
