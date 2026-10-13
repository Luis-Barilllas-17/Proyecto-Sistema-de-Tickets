<?php

namespace Database\Seeders;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentSecurityControl;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketPriority;
use App\Models\TicketSequence;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModuleFiveSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $profiles = [
            'super-administrador' => $permissions->keys()->all(),
            'jefatura-it' => $permissions->keys()->all(),
            'encargado-area-it' => [
                'development.view', 'development.update', 'development.requirements', 'development.security_manage',
                'development.security_verify', 'development.tests', 'development.deploy_request',
            ],
            'programador-it' => [
                'development.view', 'development.create', 'development.update', 'development.requirements',
                'development.security_manage', 'development.tests', 'development.deploy_request',
            ],
            'tecnico-it' => ['development.view', 'development.tests'],
            'jefatura-area-solicitante' => ['development.view', 'development.create'],
            'usuario-solicitante' => ['development.view', 'development.create'],
        ];

        foreach ($profiles as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->first()?->permissions()
                ->syncWithoutDetaching($permissions->only($permissionSlugs)->pluck('id'));
        }

        $this->seedDemoRequest();
    }

    private function seedPermissions()
    {
        return collect([
            ['Consultar desarrollo', 'Consultar solicitudes de desarrollo bajo el alcance del usuario', 'development.view'],
            ['Crear solicitud', 'Registrar solicitudes especializadas de desarrollo', 'development.create'],
            ['Actualizar solicitud', 'Actualizar la ficha técnica de desarrollo', 'development.update'],
            ['Gestionar requerimientos', 'Registrar y actualizar requerimientos, historias y criterios', 'development.requirements'],
            ['Gestionar seguridad', 'Documentar controles, clasificación y perfiles de acceso', 'development.security_manage'],
            ['Verificar seguridad', 'Marcar controles de seguridad como verificados', 'development.security_verify'],
            ['Registrar pruebas', 'Adjuntar evidencias funcionales, técnicas, de seguridad y aceptación', 'development.tests'],
            ['Solicitar despliegue', 'Solicitar autorización para el paso a producción', 'development.deploy_request'],
            ['Autorizar despliegue', 'Autorizar o rechazar el paso a producción y cierre', 'development.deploy_authorize'],
        ])->mapWithKeys(function (array $definition) {
            [$name, $description, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], [
                'module' => 'Tickets de desarrollo',
                'action' => $name,
                'name' => $name,
                'description' => $description,
            ]);

            return [$slug => $permission];
        });
    }

    private function seedDemoRequest(): void
    {
        $category = TicketCategory::where('code', 'DEV')->first();
        $subcategory = TicketSubcategory::where('ticket_category_id', $category?->id)->where('code', 'ENH')->first();
        $requester = User::where('email', 'usuario.ticket@zolicguate.com')->first();
        $developer = User::where('email', 'aplicaciones@zolicguate.com')->first();
        $group = TechnicalGroup::where('code', 'GRP-DEV')->first();
        $status = TicketStatus::where('slug', 'en-proceso')->first();
        $priority = TicketPriority::where('code', 'P3')->first();
        if (! $category || ! $subcategory || ! $requester || ! $developer || ! $group || ! $status) {
            return;
        }

        $year = (int) now()->format('Y');
        $number = sprintf('ZT-%d-%06d', $year, 4);
        $ticket = Ticket::updateOrCreate(['number' => $number], [
            'requester_id' => $requester->id,
            'created_by_id' => $requester->id,
            'source' => 'requester',
            'is_draft' => false,
            'title' => 'Mejora del módulo de control de operaciones',
            'description' => 'Digitalizar la validación y seguimiento de operaciones para reducir reprocesos y conservar trazabilidad.',
            'ticket_category_id' => $category->id,
            'ticket_subcategory_id' => $subcategory->id,
            'ticket_form_id' => TicketForm::where('code', 'FRM-DEV-REQ')->value('id'),
            'requester_area_id' => $requester->area_id,
            'requester_department_id' => $requester->department_id,
            'location' => 'Edificio administrativo',
            'attention_type' => 'development',
            'impact_level_id' => ImpactLevel::where('value', 2)->value('id'),
            'urgency_level_id' => UrgencyLevel::where('value', 2)->value('id'),
            'ticket_priority_id' => $priority?->id,
            'ticket_status_id' => $status->id,
            'approval_status' => 'approved',
            'current_it_area_id' => $group->area_id,
            'current_it_group_id' => $group->id,
            'current_assignee_id' => $developer->id,
            'assignment_status' => 'accepted',
            'submitted_at' => now()->subDays(8),
            'first_response_at' => now()->subDays(7),
            'last_activity_at' => now()->subHours(5),
        ]);

        $developmentRequest = DevelopmentRequest::updateOrCreate(['ticket_id' => $ticket->id], [
            'request_type' => 'improvement',
            'project_name' => 'Mejora del módulo de control de operaciones',
            'business_need' => 'Reducir tareas manuales y mejorar la trazabilidad de validaciones operativas.',
            'objective' => 'Incorporar un flujo digital de revisión, aprobación y consulta histórica.',
            'scope' => 'Diseño, desarrollo, pruebas y publicación de la mejora para usuarios de Operaciones.',
            'expected_benefits' => 'Menor tiempo de respuesta, reducción de errores y auditoría centralizada.',
            'affected_system' => 'Sistema institucional de operaciones',
            'expected_delivery_date' => now()->addWeeks(5)->toDateString(),
            'technical_restrictions' => 'Laravel, PHP y MySQL dentro de la infraestructura institucional.',
            'information_classification' => 'confidential',
            'contains_sensitive_data' => true,
            'sensitive_data_description' => 'Datos operativos y trazabilidad de usuarios institucionales.',
            'involves_third_parties' => false,
            'access_overview' => 'Solicitante, revisor de operaciones y administrador funcional.',
            'repository_reference' => 'Repositorio institucional / operaciones',
            'lifecycle_status' => 'development',
        ]);

        foreach ([
            ['REQ-001', 'functional', 'Registrar validación operativa', 'El usuario autorizado podrá registrar la validación y sus observaciones.', 'El registro conserva usuario, fecha y estado.', 'high', 'implemented'],
            ['HU-001', 'user_story', 'Consultar historial', 'Como jefatura deseo consultar el historial para verificar el avance de cada operación.', 'La búsqueda permite filtrar por fecha, estado y responsable.', 'medium', 'approved'],
            ['SEG-001', 'security', 'Control de acceso por perfil', 'Cada acción deberá validarse con permisos del lado del servidor.', 'Un usuario sin permiso recibe una respuesta 403 y no modifica datos.', 'critical', 'implemented'],
        ] as [$code, $type, $title, $description, $criteria, $requirementPriority, $requirementStatus]) {
            $developmentRequest->requirements()->updateOrCreate(['code' => $code], [
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'acceptance_criteria' => $criteria,
                'priority' => $requirementPriority,
                'status' => $requirementStatus,
                'created_by_id' => $developer->id,
                'updated_by_id' => $developer->id,
            ]);
        }

        foreach (DevelopmentSecurityControl::TYPES as $type => $label) {
            $required = in_array($type, ['authentication', 'authorization', 'activity_logging', 'input_validation', 'error_handling'], true);
            $developmentRequest->securityControls()->updateOrCreate(['control_type' => $type], [
                'is_required' => $required,
                'requirement_details' => $required ? 'Aplicar '.$label.' según las normas institucionales y OWASP.' : null,
                'implementation_notes' => $required ? 'Control contemplado en el diseño técnico.' : null,
                'status' => $required ? 'implemented' : 'not_applicable',
            ]);
        }

        $developmentRequest->accessProfiles()->updateOrCreate(['name' => 'Revisor de operaciones'], [
            'description' => 'Perfil encargado de validar los registros de su área.',
            'permissions' => 'Consultar solicitudes, aprobar o devolver validaciones y consultar historial.',
            'data_scope' => 'Únicamente registros del área de Operaciones.',
            'includes_sensitive_data' => true,
            'third_party_access' => false,
            'created_by_id' => $developer->id,
        ]);

        $ticket->histories()->firstOrCreate(['event' => 'development_demo_seeded'], [
            'user_id' => null,
            'description' => 'Solicitud demostrativa creada para el Módulo 5.',
            'created_at' => now()->subDays(8),
        ]);

        $sequence = TicketSequence::firstOrCreate(['year' => $year], ['last_number' => 0]);
        if ($sequence->last_number < 4) {
            $sequence->update(['last_number' => 4]);
        }
    }
}
