<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Department;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\User;
use Illuminate\View\View;

class AdministrationDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('administration.dashboard', [
            'metrics' => [
                'users' => User::count(),
                'active_users' => User::where('status', 'active')->count(),
                'roles' => Role::where('is_active', true)->count(),
                'areas' => Area::where('is_active', true)->count(),
                'departments' => Department::where('is_active', true)->count(),
                'groups' => TechnicalGroup::where('is_active', true)->count(),
            ],
            'recentUsers' => User::with(['roles', 'area', 'department'])->latest()->limit(6)->get(),
            'roles' => Role::withCount('users')->orderByDesc('users_count')->get(),
            'itAreas' => Area::withCount(['users', 'technicalGroups'])
                ->where('type', 'it')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
