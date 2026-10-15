<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    #[DataProvider('activeAccountProvider')]
    public function test_active_seeded_accounts_can_log_in_and_log_out(
        string $email,
        string $password,
        string $role,
    ): void {
        $response = $this->post(route('login.store'), [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
        $this->assertTrue(auth()->user()->hasRole($role));
        $this->get(route('dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[DataProvider('blockedAccountProvider')]
    public function test_disabled_or_locked_seeded_accounts_cannot_log_in(
        string $email,
        string $password,
    ): void {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => $email,
            'password' => $password,
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[DataProvider('roleProvider')]
    public function test_each_profile_can_only_open_the_surfaces_granted_to_it(string $role): void
    {
        $user = $this->userForRole($role);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        foreach ($this->permissionProtectedPages() as $routeName => $permission) {
            $response = $this->actingAs($user)->get(route($routeName));

            if ($user->hasPermission($permission)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_ticket_visibility_is_limited_to_owner_or_assigned_technical_scope(): void
    {
        $ticket = Ticket::where('requester_id', User::where('email', 'usuario.ticket@zolicguate.com')->value('id'))
            ->whereNotNull('current_assignee_id')
            ->firstOrFail();
        $owner = User::where('email', 'usuario.ticket@zolicguate.com')->firstOrFail();
        $assignedTechnician = User::findOrFail($ticket->current_assignee_id);
        $unrelatedRequester = $this->userForRole('usuario-solicitante', true);

        $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($assignedTechnician)->get(route('tickets.show', $ticket))->assertOk();
        $this->actingAs($unrelatedRequester)->get(route('tickets.show', $ticket))->assertForbidden();
    }

    public function test_requester_has_a_distinct_personal_portal_without_changing_admin_dashboard(): void
    {
        $requester = User::where('email', 'usuario.ticket@zolicguate.com')->firstOrFail();
        $admin = User::where('email', 'admin@zolicguate.com')->firstOrFail();

        $this->actingAs($requester)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Portal del solicitante')
            ->assertSee('Solicitudes recientes')
            ->assertSee('Mi inicio')
            ->assertSee('name="sidebar-navigation"', false)
            ->assertSee('<summary class="nav-group__toggle">', false)
            ->assertSee('method="POST" class="sidebar__logout" data-logout-form', false)
            ->assertDontSee('class="nav-group is-current" open', false)
            ->assertDontSee('Resumen de seguridad');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Resumen de seguridad')
            ->assertSee('Seguridad de acceso')
            ->assertSee('Administración')
            ->assertSee('Configuración')
            ->assertDontSee('Portal del solicitante');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function activeAccountProvider(): array
    {
        return [
            'super administrator' => ['admin@zolicguate.com', 'Zolic#2026', 'super-administrador'],
            'it management' => ['jefatura.it@zolicguate.com', 'Jefatura#2026', 'jefatura-it'],
            'infrastructure technician' => ['infraestructura@zolicguate.com', 'Infra#2026', 'tecnico-it'],
            'support technician' => ['soporte@zolicguate.com', 'Soporte#2026', 'tecnico-it'],
            'programmer' => ['aplicaciones@zolicguate.com', 'Apps#2026A', 'programador-it'],
            'requester' => ['usuario.ticket@zolicguate.com', 'Tickets#2026', 'usuario-solicitante'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function blockedAccountProvider(): array
    {
        return [
            'suspended account' => ['temporal@zolicguate.com', 'Temporal#2026'],
            'inactive account' => ['inactivo@zolicguate.com', 'Inactivo#2026'],
            'temporarily locked account' => ['control.acceso@zolicguate.com', 'Control#2026'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleProvider(): array
    {
        return [
            'super administrator' => ['super-administrador'],
            'it management' => ['jefatura-it'],
            'it area manager' => ['encargado-area-it'],
            'technician' => ['tecnico-it'],
            'programmer' => ['programador-it'],
            'requesting area management' => ['jefatura-area-solicitante'],
            'requester' => ['usuario-solicitante'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function permissionProtectedPages(): array
    {
        return [
            'tickets.index' => 'tickets.view',
            'tickets.create' => 'tickets.create',
            'operations.index' => 'operations.workbench',
            'development.index' => 'development.view',
            'development.create' => 'development.create',
            'inventory.index' => 'inventory.view',
            'inventory.equipment.create' => 'inventory.equipment_manage',
            'inventory.supplies.index' => 'inventory.view',
            'inventory.supplies.create' => 'inventory.stock_manage',
            'maintenance.index' => 'maintenance.view',
            'maintenance.plans.create' => 'maintenance.manage_plans',
            'notifications.index' => 'notifications.view',
            'reports.index' => 'reports.view',
            'security.index' => 'security.view',
            'administration.dashboard' => 'users.view',
            'administration.users.index' => 'users.view',
            'administration.users.create' => 'users.create',
            'administration.roles.index' => 'roles.view',
            'administration.organization.index' => 'organization.view',
            'administration.groups.index' => 'groups.view',
            'configuration.dashboard' => 'configuration.view',
            'configuration.categories.index' => 'configuration.view',
            'configuration.forms.index' => 'configuration.view',
            'configuration.approvals.index' => 'configuration.view',
            'configuration.routing.index' => 'configuration.view',
            'configuration.service-levels.index' => 'configuration.view',
        ];
    }

    private function userForRole(string $roleSlug, bool $forceNew = false): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        if (! $forceNew) {
            $user = User::where('status', 'active')
                ->whereNull('locked_until')
                ->whereHas('roles', fn ($query) => $query->where('slug', $roleSlug))
                ->first();

            if ($user) {
                return $user;
            }
        }

        $user = User::factory()->create([
            'email' => fake()->unique()->userName().'@zolicguate.com',
            'status' => 'active',
            'is_super_admin' => false,
            'locked_until' => null,
            'force_password_change' => false,
            'password_changed_at' => now(),
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
