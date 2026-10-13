<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\TechnicalGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModuleTwoSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['module' => 'Seguridad y acceso', 'action' => 'Consultar', 'name' => 'Consultar seguridad', 'slug' => 'security.view'],
            ['module' => 'Seguridad y acceso', 'action' => 'Administrar', 'name' => 'Administrar seguridad', 'slug' => 'security.manage'],
            ['module' => 'Usuarios', 'action' => 'Consultar', 'name' => 'Consultar usuarios', 'slug' => 'users.view'],
            ['module' => 'Usuarios', 'action' => 'Crear', 'name' => 'Crear usuarios', 'slug' => 'users.create'],
            ['module' => 'Usuarios', 'action' => 'Editar', 'name' => 'Editar usuarios', 'slug' => 'users.update'],
            ['module' => 'Usuarios', 'action' => 'Cambiar estado', 'name' => 'Activar y desactivar usuarios', 'slug' => 'users.status'],
            ['module' => 'Usuarios', 'action' => 'Restablecer acceso', 'name' => 'Restablecer contraseñas', 'slug' => 'users.reset_password'],
            ['module' => 'Roles y permisos', 'action' => 'Consultar', 'name' => 'Consultar roles', 'slug' => 'roles.view'],
            ['module' => 'Roles y permisos', 'action' => 'Crear', 'name' => 'Crear roles', 'slug' => 'roles.create'],
            ['module' => 'Roles y permisos', 'action' => 'Editar', 'name' => 'Editar roles y permisos', 'slug' => 'roles.update'],
            ['module' => 'Roles y permisos', 'action' => 'Asignar', 'name' => 'Asignar perfiles', 'slug' => 'roles.assign'],
            ['module' => 'Estructura organizacional', 'action' => 'Consultar', 'name' => 'Consultar estructura', 'slug' => 'organization.view'],
            ['module' => 'Estructura organizacional', 'action' => 'Crear', 'name' => 'Crear áreas y departamentos', 'slug' => 'organization.create'],
            ['module' => 'Estructura organizacional', 'action' => 'Editar', 'name' => 'Editar áreas y departamentos', 'slug' => 'organization.update'],
            ['module' => 'Grupos técnicos', 'action' => 'Consultar', 'name' => 'Consultar grupos técnicos', 'slug' => 'groups.view'],
            ['module' => 'Grupos técnicos', 'action' => 'Crear', 'name' => 'Crear grupos técnicos', 'slug' => 'groups.create'],
            ['module' => 'Grupos técnicos', 'action' => 'Editar', 'name' => 'Editar grupos técnicos', 'slug' => 'groups.update'],
            ['module' => 'Grupos técnicos', 'action' => 'Asignar', 'name' => 'Asignar integrantes', 'slug' => 'groups.assign'],
            ['module' => 'Especialidades', 'action' => 'Administrar', 'name' => 'Administrar especialidades', 'slug' => 'specialties.manage'],
        ])->mapWithKeys(function (array $permission) {
            $model = Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission + ['description' => $permission['name']],
            );

            return [$model->slug => $model];
        });

        $roleDefinitions = [
            'super-administrador' => ['Super Administrador', 'Control total del sistema.', $permissions->keys()->all()],
            'jefatura-it' => ['Jefatura de IT', 'Dirección operativa del Departamento de IT.', [
                'security.view', 'users.view', 'users.create', 'users.update', 'users.status', 'users.reset_password',
                'organization.view', 'organization.create', 'organization.update',
                'groups.view', 'groups.create', 'groups.update', 'groups.assign', 'specialties.manage',
            ]],
            'encargado-area-it' => ['Encargado de Área de IT', 'Responsable de una subdivisión técnica de IT.', [
                'users.view', 'users.update', 'organization.view', 'groups.view', 'groups.update', 'groups.assign', 'specialties.manage',
            ]],
            'tecnico-it' => ['Técnico IT', 'Personal de soporte e infraestructura.', ['organization.view', 'groups.view']],
            'programador-it' => ['Programador IT', 'Personal de desarrollo y mantenimiento de sistemas.', ['organization.view', 'groups.view']],
            'jefatura-area-solicitante' => ['Jefatura de Área Solicitante', 'Jefatura de un área usuaria de los servicios de IT.', ['users.view', 'organization.view']],
            'usuario-solicitante' => ['Usuario Solicitante', 'Usuario institucional que registra solicitudes.', []],
        ];

        $roles = collect();
        foreach ($roleDefinitions as $slug => [$name, $description, $permissionSlugs]) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'is_system' => true, 'is_active' => true],
            );
            $role->permissions()->sync($permissions->only($permissionSlugs)->pluck('id'));
            $roles->put($slug, $role);
        }

        $users = User::whereIn('email', [
            'admin@zolicguate.com', 'jefatura.it@zolicguate.com', 'infraestructura@zolicguate.com',
            'aplicaciones@zolicguate.com', 'soporte@zolicguate.com', 'solicitante@zolicguate.com',
            'temporal@zolicguate.com', 'inactivo@zolicguate.com', 'control.acceso@zolicguate.com',
        ])->get()->keyBy('email');

        $areaDefinitions = [
            ['GER', 'Gerencia General', 'organizational', null, 'admin@zolicguate.com'],
            ['IT', 'Tecnología de la Información', 'organizational', null, 'jefatura.it@zolicguate.com'],
            ['AF', 'Administración y Finanzas', 'organizational', null, null],
            ['OPE', 'Operaciones', 'organizational', null, 'control.acceso@zolicguate.com'],
            ['RH', 'Recursos Humanos', 'organizational', null, null],
        ];

        $areas = collect();
        foreach ($areaDefinitions as [$code, $name, $type, $parent, $headEmail]) {
            $area = Area::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parent,
                'head_user_id' => $headEmail ? $users->get($headEmail)?->id : null,
                'description' => 'Unidad organizacional de '.$name.'.',
                'is_active' => true,
            ]);
            $areas->put($code, $area);
        }

        foreach ([
            ['IT-DEV', 'Desarrollo', 'aplicaciones@zolicguate.com'],
            ['IT-INF', 'Infraestructura', 'infraestructura@zolicguate.com'],
            ['IT-SOP', 'Soporte Técnico', 'soporte@zolicguate.com'],
        ] as [$code, $name, $headEmail]) {
            $area = Area::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => 'it',
                'parent_id' => $areas->get('IT')->id,
                'head_user_id' => $users->get($headEmail)?->id,
                'description' => 'Área interna de IT: '.$name.'.',
                'is_active' => true,
            ]);
            $areas->put($code, $area);
        }

        $departmentDefinitions = [
            ['DEV-APP', 'Desarrollo y Aplicaciones', 'IT-DEV', 'aplicaciones@zolicguate.com'],
            ['INF-RED', 'Redes y Servidores', 'IT-INF', 'infraestructura@zolicguate.com'],
            ['SOP-MDA', 'Mesa de Ayuda', 'IT-SOP', 'soporte@zolicguate.com'],
            ['AF-CON', 'Contabilidad', 'AF', null],
            ['OPE-POR', 'Operaciones Portuarias', 'OPE', 'control.acceso@zolicguate.com'],
            ['RH-TH', 'Talento Humano', 'RH', null],
        ];

        $departments = collect();
        foreach ($departmentDefinitions as [$code, $name, $areaCode, $headEmail]) {
            $department = Department::updateOrCreate(['code' => $code], [
                'name' => $name,
                'area_id' => $areas->get($areaCode)->id,
                'head_user_id' => $headEmail ? $users->get($headEmail)?->id : null,
                'description' => 'Departamento de '.$name.'.',
                'is_active' => true,
            ]);
            $departments->put($code, $department);
        }

        $assignments = [
            'admin@zolicguate.com' => ['ADM-001', 'Super Administrador', '101', 'GER', null, 'super-administrador'],
            'jefatura.it@zolicguate.com' => ['IT-001', 'Jefatura de IT', '201', 'IT', null, 'jefatura-it'],
            'infraestructura@zolicguate.com' => ['IT-014', 'Técnico de infraestructura', '214', 'IT-INF', 'INF-RED', 'tecnico-it'],
            'aplicaciones@zolicguate.com' => ['IT-021', 'Programador de aplicaciones', '221', 'IT-DEV', 'DEV-APP', 'programador-it'],
            'soporte@zolicguate.com' => ['IT-008', 'Técnico de mesa de ayuda', '208', 'IT-SOP', 'SOP-MDA', 'tecnico-it'],
            'solicitante@zolicguate.com' => ['OPE-042', 'Analista de operaciones', '342', 'OPE', 'OPE-POR', 'usuario-solicitante'],
            'temporal@zolicguate.com' => ['AF-099', 'Asistente administrativo', '399', 'AF', 'AF-CON', 'usuario-solicitante'],
            'inactivo@zolicguate.com' => ['RH-071', 'Auxiliar de talento humano', '371', 'RH', 'RH-TH', 'usuario-solicitante'],
            'control.acceso@zolicguate.com' => ['OPE-006', 'Jefatura de operaciones', '306', 'OPE', 'OPE-POR', 'jefatura-area-solicitante'],
        ];

        foreach ($assignments as $email => [$employeeCode, $jobTitle, $extension, $areaCode, $departmentCode, $roleSlug]) {
            $user = $users->get($email);
            if (! $user) {
                continue;
            }

            $user->forceFill([
                'employee_code' => $employeeCode,
                'job_title' => $jobTitle,
                'phone_extension' => $extension,
                'area_id' => $areas->get($areaCode)?->id,
                'department_id' => $departmentCode ? $departments->get($departmentCode)?->id : null,
            ])->save();
            $user->roles()->sync([$roles->get($roleSlug)->id]);
        }

        $groups = collect();
        foreach ([
            ['GRP-INF', 'Infraestructura y Redes', 'IT-INF', 'infraestructura@zolicguate.com'],
            ['GRP-DEV', 'Desarrollo de Sistemas', 'IT-DEV', 'aplicaciones@zolicguate.com'],
            ['GRP-SOP', 'Mesa de Ayuda', 'IT-SOP', 'soporte@zolicguate.com'],
        ] as [$code, $name, $areaCode, $coordinatorEmail]) {
            $group = TechnicalGroup::updateOrCreate(['code' => $code], [
                'name' => $name,
                'area_id' => $areas->get($areaCode)->id,
                'description' => 'Grupo técnico de '.$name.'.',
                'coordinator_user_id' => $users->get($coordinatorEmail)?->id,
                'is_active' => true,
            ]);
            $groups->put($code, $group);
        }

        $groups->get('GRP-INF')->members()->sync([$users->get('infraestructura@zolicguate.com')->id]);
        $groups->get('GRP-DEV')->members()->sync([$users->get('aplicaciones@zolicguate.com')->id]);
        $groups->get('GRP-SOP')->members()->sync([
            $users->get('soporte@zolicguate.com')->id,
            $users->get('infraestructura@zolicguate.com')->id,
        ]);

        $specialties = collect();
        foreach ([
            ['Redes y conectividad', 'technical'],
            ['Servidores y virtualización', 'technical'],
            ['Soporte a usuarios', 'technical'],
            ['Desarrollo PHP y Laravel', 'programming'],
            ['Bases de datos MySQL', 'programming'],
            ['Seguridad de aplicaciones', 'technical'],
        ] as [$name, $type]) {
            $specialty = Specialty::updateOrCreate(['name' => $name], [
                'type' => $type,
                'description' => 'Especialidad institucional en '.$name.'.',
                'is_active' => true,
            ]);
            $specialties->put($name, $specialty);
        }

        $users->get('infraestructura@zolicguate.com')->specialties()->sync([
            $specialties->get('Redes y conectividad')->id => ['proficiency_level' => 'advanced', 'is_primary' => true],
            $specialties->get('Servidores y virtualización')->id => ['proficiency_level' => 'advanced', 'is_primary' => false],
        ]);
        $users->get('aplicaciones@zolicguate.com')->specialties()->sync([
            $specialties->get('Desarrollo PHP y Laravel')->id => ['proficiency_level' => 'advanced', 'is_primary' => true],
            $specialties->get('Bases de datos MySQL')->id => ['proficiency_level' => 'intermediate', 'is_primary' => false],
            $specialties->get('Seguridad de aplicaciones')->id => ['proficiency_level' => 'intermediate', 'is_primary' => false],
        ]);
        $users->get('soporte@zolicguate.com')->specialties()->sync([
            $specialties->get('Soporte a usuarios')->id => ['proficiency_level' => 'advanced', 'is_primary' => true],
        ]);
    }
}
