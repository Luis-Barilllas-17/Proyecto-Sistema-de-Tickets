<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_available_and_root_redirects_to_it(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Bienvenido de nuevo')
            ->assertSee('Correo institucional');
    }

    public function test_active_user_can_login_and_access_is_audited(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@zolicguate.com',
            'password' => Hash::make('Acceso#2026'),
        ]);

        $this->post(route('login.store'), [
            'email' => 'USUARIO@ZOLICGUATE.COM',
            'password' => 'Acceso#2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'login_success',
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_non_institutional_email_is_rejected(): void
    {
        $this->post(route('login.store'), [
            'email' => 'persona@gmail.com',
            'password' => 'Acceso#2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_even_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'inactivo@zolicguate.com',
            'password' => Hash::make('Acceso#2026'),
            'status' => 'inactive',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Acceso#2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'login_blocked',
        ]);
    }

    public function test_account_is_temporarily_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'bloqueo@zolicguate.com',
            'password' => Hash::make('Correcta#2026'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'Incorrecta#2026',
            ])->assertSessionHasErrors('email');
        }

        $user->refresh();

        $this->assertTrue($user->isLocked());
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'account_locked',
        ]);
    }

    public function test_active_user_can_request_a_password_recovery_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'recuperacion@zolicguate.com']);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password_reset_requested',
        ]);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'cambio@zolicguate.com']);
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NuevaClave#2026', $user->fresh()->password));
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password_reset_completed',
        ]);
    }

    public function test_forced_password_change_prevents_access_to_panel_until_completed(): void
    {
        $user = User::factory()->create([
            'email' => 'temporal@zolicguate.com',
            'force_password_change' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $this->actingAs($user)
            ->put(route('password.update'), [
                'password' => 'Personal#2026',
                'password_confirmation' => 'Personal#2026',
            ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->force_password_change);
    }

    public function test_session_expires_after_configured_inactivity(): void
    {
        $user = User::factory()->create(['email' => 'sesion@zolicguate.com']);

        $this->actingAs($user)
            ->withSession(['security_last_activity' => now()->subMinutes(31)->timestamp])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'logout_inactivity',
        ]);
    }

    public function test_super_admin_can_reset_password_and_close_user_sessions(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@zolicguate.com',
            'is_super_admin' => true,
        ]);
        $user = User::factory()->create(['email' => 'soporte@zolicguate.com']);

        DB::table('sessions')->insert([
            'id' => 'remote-session',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.8',
            'user_agent' => 'Test browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->put(route('security.password.update', $user), [
                'password' => 'Temporal#2026',
                'password_confirmation' => 'Temporal#2026',
            ])->assertRedirect(route('security.index'));

        $user->refresh();
        $this->assertTrue($user->force_password_change);
        $this->assertTrue(Hash::check('Temporal#2026', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'remote-session']);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'password_admin_reset',
        ]);
    }

    public function test_super_admin_can_suspend_an_account_but_not_their_own(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@zolicguate.com',
            'is_super_admin' => true,
        ]);
        $user = User::factory()->create(['email' => 'usuario@zolicguate.com']);

        $this->actingAs($admin)
            ->patch(route('security.users.status', $user), ['status' => 'suspended'])
            ->assertSessionHas('success');

        $this->assertSame('suspended', $user->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('security.users.status', $admin), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_super_admin_can_unlock_a_temporarily_locked_account(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.desbloqueo@zolicguate.com',
            'is_super_admin' => true,
        ]);
        $user = User::factory()->create([
            'email' => 'bloqueado@zolicguate.com',
            'failed_login_attempts' => 4,
            'locked_until' => now()->addMinutes(10),
            'last_failed_login_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('security.users.unlock', $user))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNull($user->locked_until);
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'account_unlocked',
        ]);
    }

    public function test_regular_user_cannot_open_administrative_security_console(): void
    {
        $user = User::factory()->create(['email' => 'regular@zolicguate.com']);

        $this->actingAs($user)
            ->get(route('security.index'))
            ->assertForbidden();
    }

    public function test_authenticated_views_render_for_user_and_super_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin.vistas@zolicguate.com',
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cuenta protegida')
            ->assertSee('Dispositivos con acceso');

        $this->actingAs($admin)
            ->get(route('security.index'))
            ->assertOk()
            ->assertSee('Cuentas institucionales')
            ->assertSee('Bitácora de seguridad');

        $this->actingAs($admin)
            ->get(route('security.password.edit', $admin))
            ->assertOk()
            ->assertSee('Restablecer contraseña');
    }

    public function test_logout_closes_session_and_is_audited(): void
    {
        $user = User::factory()->create(['email' => 'salida@zolicguate.com']);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(SecurityEvent::where('user_id', $user->id)->where('event', 'logout')->exists());
    }
}
