<?php

namespace Database\Seeders;

use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\Support\DemoNetworkAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SecurityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $demoAddresses = [
            'admin' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 10),
            'infrastructure' => DemoNetworkAddress::ipv4(DemoNetworkAddress::INFRASTRUCTURE, 34),
            'applications' => DemoNetworkAddress::ipv4(DemoNetworkAddress::DEVELOPMENT, 46),
            'it_management' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 18),
            'temporary' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 77),
            'inactive' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 21),
            'locked' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 63),
            'support' => DemoNetworkAddress::ipv4(DemoNetworkAddress::INFRASTRUCTURE, 25),
            'blocked' => DemoNetworkAddress::ipv4(DemoNetworkAddress::OPERATIONS, 91),
        ];

        $accounts = [
            [
                'name' => 'Técnico de infraestructura',
                'email' => 'infraestructura@zolicguate.com',
                'password' => 'Infra#2026',
                'status' => 'active',
                'last_login_at' => now()->subMinutes(48),
                'last_login_ip' => $demoAddresses['infrastructure'],
            ],
            [
                'name' => 'Soporte de aplicaciones',
                'email' => 'aplicaciones@zolicguate.com',
                'password' => 'Apps#2026A',
                'status' => 'active',
                'last_login_at' => now()->subHours(3),
                'last_login_ip' => $demoAddresses['applications'],
            ],
            [
                'name' => 'Jefatura de IT',
                'email' => 'jefatura.it@zolicguate.com',
                'password' => 'Jefatura#2026',
                'status' => 'active',
                'last_login_at' => now()->subDay(),
                'last_login_ip' => $demoAddresses['it_management'],
            ],
            [
                'name' => 'Cuenta temporal suspendida',
                'email' => 'temporal@zolicguate.com',
                'password' => 'Temporal#2026',
                'status' => 'suspended',
                'last_login_at' => now()->subDays(4),
                'last_login_ip' => $demoAddresses['temporary'],
            ],
            [
                'name' => 'Cuenta fuera de servicio',
                'email' => 'inactivo@zolicguate.com',
                'password' => 'Inactivo#2026',
                'status' => 'inactive',
                'last_login_at' => now()->subDays(12),
                'last_login_ip' => $demoAddresses['inactive'],
            ],
            [
                'name' => 'Cuenta con bloqueo temporal',
                'email' => 'control.acceso@zolicguate.com',
                'password' => 'Control#2026',
                'status' => 'active',
                'last_login_at' => now()->subDays(2),
                'last_login_ip' => $demoAddresses['locked'],
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes(12),
                'last_failed_login_at' => now()->subMinutes(3),
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'status' => $account['status'],
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                    'password_changed_at' => now()->subDays(24),
                    'last_login_at' => $account['last_login_at'],
                    'last_login_ip' => $account['last_login_ip'],
                    'failed_login_attempts' => $account['failed_login_attempts'] ?? 0,
                    'locked_until' => $account['locked_until'] ?? null,
                    'last_failed_login_at' => $account['last_failed_login_at'] ?? null,
                ],
            );
        }

        SecurityEvent::whereJsonContains('details->source', 'demo')->delete();

        $this->event('admin@zolicguate.com', 'login_success', $demoAddresses['admin'], now()->subMinutes(12));
        $this->event('infraestructura@zolicguate.com', 'login_success', $demoAddresses['infrastructure'], now()->subMinutes(48));
        $this->event('aplicaciones@zolicguate.com', 'login_success', $demoAddresses['applications'], now()->subHours(3));
        $this->event('aplicaciones@zolicguate.com', 'login_failed', $demoAddresses['applications'], now()->subHours(3)->subMinutes(2), ['attempt' => 1]);
        $this->event('jefatura.it@zolicguate.com', 'logout', $demoAddresses['it_management'], now()->subHours(8));
        $this->event('soporte@zolicguate.com', 'password_reset_completed', $demoAddresses['support'], now()->subDay());
        $this->event('soporte@zolicguate.com', 'password_reset_requested', $demoAddresses['support'], now()->subDay()->subMinutes(4));
        $this->event('temporal@zolicguate.com', 'account_suspended', $demoAddresses['admin'], now()->subDays(2));
        $this->event('inactivo@zolicguate.com', 'account_inactive', $demoAddresses['admin'], now()->subDays(3));
        $this->event('infraestructura@zolicguate.com', 'password_changed', $demoAddresses['infrastructure'], now()->subDays(5));
        $this->event('solicitante@zolicguate.com', 'login_blocked', $demoAddresses['blocked'], now()->subDays(6), ['reason' => 'inactive_account']);
        $this->event('control.acceso@zolicguate.com', 'account_locked', $demoAddresses['locked'], now()->subMinutes(3), ['attempt' => 5]);
    }

    private function event(string $email, string $event, string $ip, $createdAt, array $details = []): void
    {
        $user = User::where('email', $email)->first();

        SecurityEvent::create([
            'user_id' => $user?->id,
            'email' => $email,
            'event' => $event,
            'ip_address' => $ip,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ZOLIC-Demo',
            'details' => ['source' => 'demo', ...$details],
            'created_at' => $createdAt,
        ]);
    }
}
