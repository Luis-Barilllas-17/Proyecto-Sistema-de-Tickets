<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Department;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketCategory;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketSequence;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ModuleFourSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $profiles = [
            'super-administrador' => $permissions->keys()->all(),
            'jefatura-it' => $permissions->keys()->all(),
            'encargado-area-it' => [
                'tickets.view', 'tickets.view_all', 'tickets.view_assigned', 'tickets.create', 'tickets.create_internal',
                'tickets.assign', 'tickets.transfer', 'tickets.accept_assignment', 'tickets.comment',
                'tickets.internal_note', 'tickets.activity', 'tickets.status', 'tickets.solve', 'tickets.relate',
            ],
            'tecnico-it' => [
                'tickets.view', 'tickets.view_assigned', 'tickets.create', 'tickets.create_internal',
                'tickets.accept_assignment', 'tickets.comment', 'tickets.internal_note', 'tickets.activity',
                'tickets.status', 'tickets.solve', 'tickets.relate',
            ],
            'programador-it' => [
                'tickets.view', 'tickets.view_assigned', 'tickets.create', 'tickets.create_internal',
                'tickets.accept_assignment', 'tickets.comment', 'tickets.internal_note', 'tickets.activity',
                'tickets.status', 'tickets.solve', 'tickets.relate',
            ],
            'jefatura-area-solicitante' => [
                'tickets.view', 'tickets.create', 'tickets.approve', 'tickets.comment',
                'tickets.review', 'tickets.reopen', 'tickets.relate',
            ],
            'usuario-solicitante' => [
                'tickets.view', 'tickets.create', 'tickets.comment', 'tickets.review', 'tickets.reopen', 'tickets.relate',
            ],
        ];

        foreach ($profiles as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->first()?->permissions()
                ->syncWithoutDetaching($permissions->only($permissionSlugs)->pluck('id'));
        }

        $this->seedDemoTickets();
    }

    private function seedPermissions()
    {
        return collect([
            ['Consultar tickets', 'Consultar tickets permitidos', 'tickets.view'],
            ['Visibilidad global', 'Consultar todos los tickets', 'tickets.view_all'],
            ['Bandeja asignada', 'Consultar tickets asignados al técnico o grupo', 'tickets.view_assigned'],
            ['Crear tickets', 'Crear tickets y borradores', 'tickets.create'],
            ['Crear tickets internos', 'Registrar actividades internas de IT como ticket', 'tickets.create_internal'],
            ['Aprobar tickets', 'Aprobar, rechazar o devolver tickets', 'tickets.approve'],
            ['Asignar tickets', 'Asignar tickets a áreas, grupos y técnicos', 'tickets.assign'],
            ['Transferir tickets', 'Reasignar y transferir tickets', 'tickets.transfer'],
            ['Responder asignaciones', 'Aceptar o rechazar asignaciones', 'tickets.accept_assignment'],
            ['Comentar tickets', 'Publicar comentarios visibles', 'tickets.comment'],
            ['Notas internas', 'Consultar y registrar notas internas', 'tickets.internal_note'],
            ['Registrar actividades', 'Documentar trabajo, diagnóstico y tiempo', 'tickets.activity'],
            ['Cambiar estados', 'Gestionar estados operativos y cancelación', 'tickets.status'],
            ['Registrar solución', 'Documentar y remitir soluciones', 'tickets.solve'],
            ['Aceptar servicio', 'Aceptar o rechazar la solución y calificar', 'tickets.review'],
            ['Reabrir tickets', 'Reabrir tickets solucionados o cerrados', 'tickets.reopen'],
            ['Relacionar tickets', 'Vincular casos relacionados o duplicados', 'tickets.relate'],
        ])->mapWithKeys(function (array $definition) {
            [$name, $description, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], [
                'module' => 'Gestión integral de tickets',
                'action' => $name,
                'name' => $name,
                'description' => $description,
            ]);

            return [$slug => $permission];
        });
    }

    private function seedDemoTickets(): void
    {
        $requesterRole = Role::where('slug', 'usuario-solicitante')->first();
        $operations = Area::where('code', 'OPE')->first();
        $department = Department::where('code', 'OPE-POR')->first();
        $requester = User::updateOrCreate(['email' => 'usuario.ticket@zolicguate.com'], [
            'name' => 'Usuario de tickets',
            'employee_code' => 'OPE-108',
            'job_title' => 'Asistente de operaciones',
            'phone_extension' => '408',
            'area_id' => $operations?->id,
            'department_id' => $department?->id,
            'password' => Hash::make('Tickets#2026'),
            'status' => 'active',
            'is_super_admin' => false,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
        ]);
        if ($requesterRole) {
            $requester->roles()->sync([$requesterRole->id]);
        }

        $technician = User::where('email', 'soporte@zolicguate.com')->first();
        $group = TechnicalGroup::where('code', 'GRP-SOP')->first();
        $itArea = Area::where('code', 'IT-SOP')->first();
        $category = TicketCategory::where('code', 'SOP')->first();
        $subcategory = TicketSubcategory::where('ticket_category_id', $category?->id)->where('code', 'SFT')->first();
        $medium = TicketPriority::where('code', 'P3')->first();
        $high = TicketPriority::where('code', 'P2')->first();
        $assigned = TicketStatus::where('slug', 'asignado')->first();
        $process = TicketStatus::where('slug', 'en-proceso')->first();
        $solved = TicketStatus::where('slug', 'solucionado')->first();

        if (! $technician || ! $category || ! $subcategory || ! $assigned || ! $process || ! $solved) {
            return;
        }

        $year = (int) now()->format('Y');
        $definitions = [
            [1, 'Instalación de software institucional', 'Se requiere instalar el cliente institucional autorizado en la estación de trabajo.', $assigned, $medium, 'pending'],
            [2, 'Intermitencia al ingresar al sistema', 'El sistema institucional presenta desconexiones durante la jornada de operaciones.', $process, $high, 'accepted'],
            [3, 'Actualización de herramienta de oficina', 'Se completó la actualización solicitada y se validó el funcionamiento con el usuario.', $solved, $medium, 'accepted'],
        ];

        foreach ($definitions as [$sequence, $title, $description, $status, $priority, $assignmentStatus]) {
            $number = sprintf('ZT-%d-%06d', $year, $sequence);
            $ticket = Ticket::updateOrCreate(['number' => $number], [
                'requester_id' => $requester->id,
                'created_by_id' => $requester->id,
                'source' => 'requester',
                'is_draft' => false,
                'title' => $title,
                'description' => $description,
                'ticket_category_id' => $category->id,
                'ticket_subcategory_id' => $subcategory->id,
                'requester_area_id' => $operations?->id,
                'requester_department_id' => $department?->id,
                'location' => 'Edificio administrativo',
                'attention_type' => 'remote',
                'impact_level_id' => ImpactLevel::where('value', 2)->value('id'),
                'urgency_level_id' => UrgencyLevel::where('value', 2)->value('id'),
                'ticket_priority_id' => $priority?->id,
                'ticket_status_id' => $status->id,
                'approval_status' => 'not_required',
                'current_it_area_id' => $itArea?->id,
                'current_it_group_id' => $group?->id,
                'current_assignee_id' => $technician->id,
                'assignment_status' => $assignmentStatus,
                'submitted_at' => now()->subDays(4 - $sequence),
                'response_due_at' => now()->addHours($sequence),
                'resolution_due_at' => now()->addDays($sequence),
                'first_response_at' => $sequence > 1 ? now()->subHours(8) : null,
                'solution' => $sequence === 3 ? 'Se actualizó la herramienta, se verificó su licencia y se realizaron pruebas funcionales satisfactorias.' : null,
                'solution_by_id' => $sequence === 3 ? $technician->id : null,
                'solved_at' => $sequence === 3 ? now()->subHour() : null,
                'last_activity_at' => now()->subHours(4 - $sequence),
            ]);

            TicketAssignment::updateOrCreate([
                'ticket_id' => $ticket->id,
                'action' => 'automatic',
            ], [
                'to_area_id' => $itArea?->id,
                'to_group_id' => $group?->id,
                'to_user_id' => $technician->id,
                'assigned_by_id' => User::where('email', 'admin@zolicguate.com')->value('id') ?: $technician->id,
                'status' => $assignmentStatus,
                'reason' => 'Asignación de demostración basada en la regla de direccionamiento.',
                'responded_at' => $assignmentStatus === 'accepted' ? now()->subHours(8) : null,
            ]);
            TicketHistory::firstOrCreate([
                'ticket_id' => $ticket->id,
                'event' => 'ticket_seeded',
            ], [
                'user_id' => null,
                'description' => 'Ticket de demostración creado para el Módulo 4.',
                'created_at' => $ticket->created_at,
            ]);
        }

        $sequence = TicketSequence::firstOrCreate(['year' => $year], ['last_number' => 0]);
        if ($sequence->last_number < 3) {
            $sequence->update(['last_number' => 3]);
        }
    }
}
