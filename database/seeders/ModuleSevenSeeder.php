<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Database\Seeder;

class ModuleSevenSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['Consultar mantenimiento', 'Consultar planes, calendario, órdenes e historial preventivo', 'maintenance.view'],
            ['Gestionar planes', 'Crear y modificar planes y listas de verificación', 'maintenance.manage_plans'],
            ['Programar equipos', 'Asignar equipos y modificar fechas preventivas', 'maintenance.schedule'],
            ['Generar órdenes', 'Generar órdenes preventivas y tickets internos asociados', 'maintenance.generate_orders'],
            ['Ejecutar mantenimiento', 'Iniciar órdenes, completar listas y adjuntar evidencias', 'maintenance.execute'],
        ])->mapWithKeys(function (array $definition) {
            [$name, $description, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], [
                'module' => 'Mantenimiento preventivo',
                'action' => $name,
                'name' => $name,
                'description' => $description,
            ]);

            return [$slug => $permission];
        });

        foreach ([
            'super-administrador' => $permissions->keys()->all(),
            'jefatura-it' => $permissions->keys()->all(),
            'encargado-area-it' => $permissions->keys()->all(),
            'tecnico-it' => ['maintenance.view', 'maintenance.execute'],
            'programador-it' => ['maintenance.view'],
        ] as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->first()?->permissions()
                ->syncWithoutDetaching($permissions->only($permissionSlugs)->pluck('id'));
        }

        $admin = User::where('email', 'admin@zolicguate.com')->first();
        if (! $admin) {
            return;
        }

        $this->seedPlan(
            $admin,
            [
                'code' => 'PM-PC-TRI',
                'name' => 'Mantenimiento trimestral de estaciones de trabajo',
                'description' => 'Programa preventivo para limpieza, diagnóstico y actualización de computadoras institucionales.',
                'frequency_months' => 3,
                'lead_days' => 14,
                'duration_minutes' => 90,
                'default_start_time' => '08:00',
                'assigned_it_area_id' => Area::where('code', 'IT-SOP')->value('id'),
                'assigned_it_group_id' => TechnicalGroup::where('code', 'GRP-SOP')->value('id'),
                'assigned_user_id' => User::where('email', 'soporte@zolicguate.com')->value('id'),
            ],
            Equipment::whereIn('inventory_code', ['ZOLIC-PC-001', 'ZOLIC-LAP-021'])->pluck('id')->all(),
            today()->addDays(5)->toDateString(),
            [
                'Inspeccionar estado físico, cables y periféricos.',
                'Realizar limpieza externa e interna segura.',
                'Verificar almacenamiento, memoria y temperatura.',
                'Aplicar actualizaciones institucionales autorizadas.',
                'Ejecutar prueba funcional y documentar resultados.',
            ],
        );

        $this->seedPlan(
            $admin,
            [
                'code' => 'PM-SRV-MEN',
                'name' => 'Revisión mensual de servidores',
                'description' => 'Verificación mensual de salud, capacidad, respaldos, registros y conectividad de servidores.',
                'frequency_months' => 1,
                'lead_days' => 7,
                'duration_minutes' => 120,
                'default_start_time' => '07:00',
                'assigned_it_area_id' => Area::where('code', 'IT-INF')->value('id'),
                'assigned_it_group_id' => TechnicalGroup::where('code', 'GRP-INF')->value('id'),
                'assigned_user_id' => User::where('email', 'infraestructura@zolicguate.com')->value('id'),
            ],
            Equipment::where('inventory_code', 'ZOLIC-SRV-001')->pluck('id')->all(),
            today()->addDays(20)->toDateString(),
            [
                'Revisar alertas y registros del sistema.',
                'Verificar capacidad de almacenamiento y memoria.',
                'Confirmar estado de respaldos y restauración.',
                'Validar servicios, puertos y conectividad.',
            ],
        );

        app(MaintenanceService::class)->generateDueOrders($admin);
    }

    private function seedPlan(User $admin, array $definition, array $equipmentIds, string $firstDueDate, array $items): void
    {
        $plan = MaintenancePlan::updateOrCreate(['code' => $definition['code']], $definition + [
            'is_active' => true,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);
        foreach ($equipmentIds as $equipmentId) {
            $assignment = $plan->assignments()->firstOrNew(['equipment_id' => $equipmentId]);
            if (! $assignment->exists) {
                $assignment->start_date = $firstDueDate;
                $assignment->next_due_date = $firstDueDate;
            }
            $assignment->is_active = true;
            $assignment->save();
        }
        foreach ($items as $index => $label) {
            $plan->checklistItems()->updateOrCreate(['label' => $label], [
                'sort_order' => ($index + 1) * 10,
                'is_required' => true,
            ]);
        }
    }
}
