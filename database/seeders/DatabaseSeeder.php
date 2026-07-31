<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@zolicguate.com'],
            [
                'name' => 'Administrador del sistema',
                'password' => Hash::make('Zolic#2026'),
                'status' => 'active',
                'is_super_admin' => true,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'soporte@zolicguate.com'],
            [
                'name' => 'Mesa de ayuda',
                'password' => Hash::make('Soporte#2026'),
                'status' => 'active',
                'is_super_admin' => false,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'solicitante@zolicguate.com'],
            [
                'name' => 'Usuario solicitante',
                'password' => Hash::make('Usuario#2026'),
                'status' => 'suspended',
                'is_super_admin' => false,
                'email_verified_at' => now(),
                'password_changed_at' => now(),
            ],
        );

        $this->call(SecurityDemoSeeder::class);
    }
}
