<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\TechnicalGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_official_brand_assets_and_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('images/brand/logo-zolic-white.png')
            ->assertDontSee('Gestión técnica')
            ->assertDontSee('Acceso protegido')
            ->assertDontSee('Trazabilidad completa')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_super_admin_can_open_every_module_two_view(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('administration.dashboard'))->assertOk()->assertSee('Personas, perfiles y estructura');
        $this->actingAs($admin)->get(route('administration.users.index'))->assertOk()->assertSee('Usuarios institucionales');
        $this->actingAs($admin)->get(route('administration.roles.index'))->assertOk()->assertSee('Roles y permisos');
        $this->actingAs($admin)->get(route('administration.organization.index'))->assertOk()->assertSee('Áreas y departamentos');
        $this->actingAs($admin)->get(route('administration.groups.index'))->assertOk()->assertSee('Grupos técnicos');
    }

    public function test_super_admin_can_create_an_institutional_user_with_role_and_location(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'Técnico IT', 'slug' => 'tecnico-it', 'is_active' => true]);
        $area = Area::create(['code' => 'IT-SOP', 'name' => 'Soporte Técnico', 'type' => 'it']);
        $department = Department::create(['area_id' => $area->id, 'code' => 'SOP-MDA', 'name' => 'Mesa de Ayuda']);
        $specialty = Specialty::create(['name' => 'Soporte a usuarios', 'type' => 'technical']);

        $this->actingAs($admin)->post(route('administration.users.store'), [
            'name' => 'Nuevo Técnico',
            'employee_code' => 'IT-100',
            'email' => 'nuevo.tecnico@zolicguate.com',
            'job_title' => 'Técnico de soporte',
            'phone_extension' => '225',
            'area_id' => $area->id,
            'department_id' => $department->id,
            'status' => 'active',
            'password' => 'Temporal#2026',
            'password_confirmation' => 'Temporal#2026',
            'role_ids' => [$role->id],
            'specialty_ids' => [$specialty->id],
        ])->assertRedirect(route('administration.users.index'));

        $user = User::where('email', 'nuevo.tecnico@zolicguate.com')->firstOrFail();
        $this->assertTrue(Hash::check('Temporal#2026', $user->password));
        $this->assertTrue($user->force_password_change);
        $this->assertSame($department->id, $user->department_id);
        $this->assertDatabaseHas('role_user', ['role_id' => $role->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('specialty_user', ['specialty_id' => $specialty->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => 'user_created']);
    }

    public function test_user_cannot_be_assigned_to_department_from_another_area(): void
    {
        $admin = $this->superAdmin();
        $role = Role::create(['name' => 'Usuario Solicitante', 'slug' => 'usuario-solicitante']);
        $area = Area::create(['code' => 'OPE', 'name' => 'Operaciones']);
        $otherArea = Area::create(['code' => 'AF', 'name' => 'Finanzas']);
        $department = Department::create(['area_id' => $otherArea->id, 'code' => 'AF-CON', 'name' => 'Contabilidad']);

        $this->actingAs($admin)->post(route('administration.users.store'), [
            'name' => 'Usuario Inválido',
            'email' => 'invalido@zolicguate.com',
            'area_id' => $area->id,
            'department_id' => $department->id,
            'status' => 'active',
            'password' => 'Temporal#2026',
            'password_confirmation' => 'Temporal#2026',
            'role_ids' => [$role->id],
        ])->assertSessionHasErrors('department_id');

        $this->assertDatabaseMissing('users', ['email' => 'invalido@zolicguate.com']);
    }

    public function test_super_admin_can_create_and_configure_a_role_permission_matrix(): void
    {
        $admin = $this->superAdmin();
        $permission = Permission::create([
            'module' => 'Usuarios', 'action' => 'Consultar', 'name' => 'Consultar usuarios', 'slug' => 'users.view',
        ]);

        $this->actingAs($admin)->post(route('administration.roles.store'), [
            'name' => 'Auditor Institucional',
            'description' => 'Perfil de consulta.',
            'is_active' => 1,
            'permission_ids' => [$permission->id],
        ])->assertRedirect(route('administration.roles.index'));

        $role = Role::where('slug', 'auditor-institucional')->firstOrFail();
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'role_created']);
    }

    public function test_server_side_permissions_allow_only_authorized_profiles(): void
    {
        $permission = Permission::create([
            'module' => 'Usuarios', 'action' => 'Consultar', 'name' => 'Consultar usuarios', 'slug' => 'users.view',
        ]);
        $role = Role::create(['name' => 'Consulta', 'slug' => 'consulta', 'is_active' => true]);
        $role->permissions()->attach($permission);
        $authorized = User::factory()->create(['email' => 'autorizado@zolicguate.com']);
        $authorized->roles()->attach($role);
        $unauthorized = User::factory()->create(['email' => 'sin.permiso@zolicguate.com']);

        $this->actingAs($authorized)->get(route('administration.users.index'))->assertOk();
        $this->actingAs($unauthorized)->get(route('administration.users.index'))->assertForbidden();
    }

    public function test_super_admin_can_register_areas_departments_and_heads(): void
    {
        $admin = $this->superAdmin();
        $head = User::factory()->create(['email' => 'jefatura@zolicguate.com']);

        $this->actingAs($admin)->post(route('administration.areas.store'), [
            'code' => 'IT-SEG', 'name' => 'Seguridad de la Información', 'type' => 'it',
            'head_user_id' => $head->id, 'description' => 'Área de seguridad.', 'is_active' => 1,
        ])->assertRedirect(route('administration.organization.index'));

        $area = Area::where('code', 'IT-SEG')->firstOrFail();

        $this->actingAs($admin)->post(route('administration.departments.store'), [
            'area_id' => $area->id, 'code' => 'SEG-APP', 'name' => 'Seguridad de Aplicaciones',
            'head_user_id' => $head->id, 'is_active' => 1,
        ])->assertRedirect(route('administration.organization.index'));

        $this->assertDatabaseHas('departments', ['code' => 'SEG-APP', 'area_id' => $area->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'area_created']);
        $this->assertDatabaseHas('security_events', ['event' => 'department_created']);
    }

    public function test_super_admin_can_create_group_assign_members_and_manage_specialties(): void
    {
        $admin = $this->superAdmin();
        $area = Area::create(['code' => 'IT-DEV', 'name' => 'Desarrollo', 'type' => 'it']);
        $member = User::factory()->create(['email' => 'programador@zolicguate.com']);

        $this->actingAs($admin)->post(route('administration.groups.store'), [
            'area_id' => $area->id,
            'code' => 'GRP-DEV',
            'name' => 'Desarrollo de Sistemas',
            'description' => 'Equipo de desarrollo.',
            'coordinator_user_id' => $member->id,
            'member_ids' => [$member->id],
            'is_active' => 1,
        ])->assertRedirect(route('administration.groups.index'));

        $group = TechnicalGroup::where('code', 'GRP-DEV')->firstOrFail();
        $this->assertDatabaseHas('group_user', ['it_group_id' => $group->id, 'user_id' => $member->id]);

        $this->actingAs($admin)->post(route('administration.specialties.store'), [
            'name' => 'Desarrollo seguro', 'type' => 'programming', 'description' => 'Prácticas OWASP.', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('specialties', ['name' => 'Desarrollo seguro', 'type' => 'programming']);
        $this->assertDatabaseHas('security_events', ['event' => 'group_created']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'email' => fake()->unique()->userName().'@zolicguate.com',
            'is_super_admin' => true,
        ]);
    }
}
