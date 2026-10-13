<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Department;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        return view('administration.organization.index', [
            'areas' => Area::with(['head', 'parent'])->withCount(['departments', 'users', 'technicalGroups'])->orderBy('type')->orderBy('name')->get(),
            'departments' => Department::with(['area', 'head'])->withCount('users')->orderBy('name')->get(),
            'heads' => User::with('roles')->where('status', 'active')->orderBy('name')->get(),
            'parentAreas' => Area::where('is_active', true)->orderBy('name')->get(),
            'editingArea' => $request->integer('edit_area') ? Area::findOrFail($request->integer('edit_area')) : null,
            'editingDepartment' => $request->integer('edit_department') ? Department::findOrFail($request->integer('edit_department')) : null,
        ]);
    }

    public function storeArea(Request $request): RedirectResponse
    {
        $validated = $this->validateArea($request);
        $area = Area::create($this->areaPayload($request, $validated));
        SecurityEvent::record('area_created', $request, details: $this->auditDetails($request, $area->id, $area->name));

        return redirect()->route('administration.organization.index')->with('success', 'Área '.$area->name.' registrada.');
    }

    public function updateArea(Request $request, Area $area): RedirectResponse
    {
        $validated = $this->validateArea($request, $area);

        if ((int) ($validated['parent_id'] ?? 0) === $area->id) {
            return back()->withInput()->withErrors(['parent_id' => 'Un área no puede depender de sí misma.']);
        }

        $area->update($this->areaPayload($request, $validated));
        SecurityEvent::record('area_updated', $request, details: $this->auditDetails($request, $area->id, $area->name));

        return redirect()->route('administration.organization.index')->with('success', 'Área actualizada.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $validated = $this->validateDepartment($request);
        $department = Department::create($this->departmentPayload($request, $validated));
        SecurityEvent::record('department_created', $request, details: $this->auditDetails($request, $department->id, $department->name));

        return redirect()->route('administration.organization.index')->with('success', 'Departamento '.$department->name.' registrado.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $validated = $this->validateDepartment($request, $department);
        $department->update($this->departmentPayload($request, $validated));
        SecurityEvent::record('department_updated', $request, details: $this->auditDetails($request, $department->id, $department->name));

        return redirect()->route('administration.organization.index')->with('success', 'Departamento actualizado.');
    }

    private function validateArea(Request $request, ?Area $area = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('areas')->ignore($area?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['organizational', 'it'])],
            'parent_id' => ['nullable', 'exists:areas,id'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'area_id' => ['required', 'exists:areas,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('departments')->ignore($department?->id)],
            'name' => ['required', 'string', 'max:255'],
            'head_user_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function areaPayload(Request $request, array $validated): array
    {
        return [
            'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'parent_id' => $validated['parent_id'] ?? null,
            'head_user_id' => $validated['head_user_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function departmentPayload(Request $request, array $validated): array
    {
        return [
            'area_id' => $validated['area_id'],
            'code' => Str::upper(trim($validated['code'])),
            'name' => trim($validated['name']),
            'head_user_id' => $validated['head_user_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function auditDetails(Request $request, int $recordId, string $recordName): array
    {
        return ['record_id' => $recordId, 'record_name' => $recordName, 'performed_by' => $request->user()->id];
    }
}
