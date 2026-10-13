<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class ModuleEightSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['Operación de tickets', 'Consultar bandejas generales, por grupo y por técnico', 'operations.workbench'],
            ['Operación de tickets', 'Escalar tickets a encargados o Jefatura de IT', 'tickets.escalate'],
            ['Operación de tickets', 'Registrar y resolver dependencias con terceros', 'tickets.third_party'],
            ['Notificaciones', 'Consultar y gestionar notificaciones propias', 'notifications.view'],
            ['Reportes', 'Consultar indicadores y reportes operativos', 'reports.view'],
            ['Reportes', 'Exportar reportes autorizados en formato CSV', 'reports.export'],
        ])->mapWithKeys(function (array $definition) {
            [$module, $name, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], [
                'module' => $module,
                'action' => $name,
                'name' => $name,
                'description' => $name,
            ]);

            return [$slug => $permission];
        });

        foreach ([
            'super-administrador' => $permissions->keys()->all(),
            'jefatura-it' => $permissions->keys()->all(),
            'encargado-area-it' => $permissions->keys()->all(),
            'tecnico-it' => ['operations.workbench', 'tickets.escalate', 'tickets.third_party', 'notifications.view', 'reports.view'],
            'programador-it' => ['operations.workbench', 'tickets.escalate', 'tickets.third_party', 'notifications.view', 'reports.view'],
            'jefatura-area-solicitante' => ['notifications.view'],
            'usuario-solicitante' => ['notifications.view'],
        ] as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->first()?->permissions()
                ->syncWithoutDetaching($permissions->only($permissionSlugs)->pluck('id'));
        }

        TicketStatus::updateOrCreate(['slug' => 'pendiente-tercero'], [
            'name' => 'Pendiente de tercero',
            'color' => '#FFC107',
            'is_initial' => false,
            'is_final' => false,
            'is_active' => true,
            'sort_order' => 65,
        ]);
    }
}
