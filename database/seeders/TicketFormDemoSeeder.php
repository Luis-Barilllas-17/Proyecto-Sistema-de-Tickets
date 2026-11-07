<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Department;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\TicketForm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TicketFormDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || realpath(config('database.connections.sqlite.database')) !== realpath(storage_path('framework/testing/ticket-forms/qa.sqlite'))) {
            throw new RuntimeException('Los datos QA de tickets solo se permiten en el entorno testing. Usa scripts/qa/prepare-ticket-forms.php.');
        }

        foreach ([
            ['admin', 'Administrador QA Tickets', 'super-administrador', 'GER', null],
            ['tecnico', 'Técnico QA Tickets', 'tecnico-it', 'IT-SOP', null],
            ['usuario', 'Usuario QA Tickets', 'usuario-solicitante', 'OPE', 'OPE-POR'],
        ] as [$account, $name, $role, $area, $department]) {
            $user = User::updateOrCreate(['email' => 'qa.tickets.'.$account.'@zolicguate.com'], [
                'name' => $name, 'employee_code' => 'QA-TICKET-'.strtoupper($account),
                'password' => Hash::make('PruebasTicket#2026'), 'status' => 'active',
                'is_super_admin' => $account === 'admin',
                'area_id' => Area::where('code', $area)->value('id'),
                'department_id' => $department ? Department::where('code', $department)->value('id') : null,
                'email_verified_at' => now(), 'password_changed_at' => now(),
                'force_password_change' => false, 'locked_until' => null, 'failed_login_attempts' => 0,
            ]);
            $user->roles()->sync([Role::where('slug', $role)->firstOrFail()->id]);
            if ($account === 'tecnico') {
                TechnicalGroup::where('code', 'GRP-SOP')->firstOrFail()->members()
                    ->syncWithoutDetaching([$user->id => ['membership_role' => 'member']]);
            }
        }

        $form = TicketForm::where('code', 'FRM-SOP-GEN')->firstOrFail();
        $form->fields()->updateOrCreate(['name' => 'qa_canal'], [
            'label' => 'Canal de contacto (QA)', 'type' => 'radio', 'options' => ['Correo', 'En sitio'],
            'is_required' => true, 'is_visible' => true, 'is_active' => true, 'sort_order' => 50,
        ]);
        $form->fields()->where('name', 'evidencia')->update(['is_required' => true]);
    }
}
