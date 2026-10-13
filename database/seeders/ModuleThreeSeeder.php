<?php

namespace Database\Seeders;

use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\Area;
use App\Models\FormField;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\PriorityMatrixRule;
use App\Models\Role;
use App\Models\RoutingRule;
use App\Models\ServiceLevelTarget;
use App\Models\TechnicalGroup;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use Illuminate\Database\Seeder;

class ModuleThreeSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        Role::where('slug', 'super-administrador')->first()?->permissions()->syncWithoutDetaching($permissions->pluck('id'));
        Role::where('slug', 'jefatura-it')->first()?->permissions()->syncWithoutDetaching(
            $permissions->only(['configuration.view', 'approvals.manage', 'routing.manage', 'service_levels.manage'])->pluck('id'),
        );
        Role::where('slug', 'encargado-area-it')->first()?->permissions()->syncWithoutDetaching(
            $permissions->only(['configuration.view', 'routing.manage'])->pluck('id'),
        );

        [$categories, $subcategories] = $this->seedCategories();
        [$priorities, $impacts, $urgencies] = $this->seedServiceLevels();
        $this->seedForms($categories);
        $this->seedApproval($categories);
        $this->seedRouting($categories);
    }

    private function seedPermissions()
    {
        return collect([
            ['Parametrización', 'Consultar', 'Consultar parametrización', 'configuration.view'],
            ['Categorías', 'Administrar', 'Administrar categorías y subcategorías', 'categories.manage'],
            ['Formularios', 'Administrar', 'Administrar formularios y campos', 'forms.manage'],
            ['Aprobaciones', 'Administrar', 'Administrar reglas y niveles de aprobación', 'approvals.manage'],
            ['Direccionamiento', 'Administrar', 'Administrar reglas de direccionamiento', 'routing.manage'],
            ['Estados, prioridades y SLA', 'Administrar', 'Administrar estados, prioridades y SLA', 'service_levels.manage'],
        ])->mapWithKeys(function (array $definition) {
            [$module, $action, $name, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], compact('module', 'action', 'name') + ['description' => $name]);

            return [$slug => $permission];
        });
    }

    private function seedCategories(): array
    {
        $categories = collect();
        foreach ([
            ['SOP', 'Soporte técnico', 'request', 'Solicitudes de asistencia técnica para usuarios y equipos.', 10],
            ['INC', 'Incidente tecnológico', 'incident', 'Interrupciones, degradaciones o eventos no planificados de servicios de IT.', 20],
            ['REQ', 'Requerimiento de IT', 'request', 'Solicitudes planificadas de accesos, equipos o servicios tecnológicos.', 30],
            ['DEV', 'Desarrollo y mejora', 'development', 'Nuevos sistemas, módulos, mejoras, correcciones y automatizaciones.', 40],
        ] as [$code, $name, $type, $description, $sortOrder]) {
            $categories->put($code, TicketCategory::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'description' => $description,
                'sort_order' => $sortOrder, 'is_active' => true,
            ]));
        }

        $subcategories = collect();
        foreach ([
            ['SOP', 'ACC', 'Acceso y credenciales', 'Problemas de acceso o credenciales institucionales.', 10],
            ['SOP', 'HWD', 'Hardware y periféricos', 'Fallas o asistencia con equipo físico.', 20],
            ['SOP', 'SFT', 'Software institucional', 'Instalación, configuración o fallas de aplicaciones.', 30],
            ['INC', 'NET', 'Red y conectividad', 'Interrupciones de red, internet o enlaces.', 10],
            ['INC', 'SRV', 'Servicio no disponible', 'Sistemas o servicios institucionales fuera de operación.', 20],
            ['INC', 'SEC', 'Evento de seguridad', 'Situaciones que afecten confidencialidad, integridad o disponibilidad.', 30],
            ['REQ', 'EQP', 'Solicitud de equipo', 'Asignación, sustitución o traslado de equipo.', 10],
            ['REQ', 'PRM', 'Permisos y accesos', 'Altas o cambios de permisos en servicios institucionales.', 20],
            ['DEV', 'NEW', 'Nuevo sistema o módulo', 'Construcción de una solución o módulo nuevo.', 10],
            ['DEV', 'ENH', 'Mejora o corrección', 'Cambio funcional o correctivo sobre una solución existente.', 20],
            ['DEV', 'AUT', 'Automatización', 'Automatización de procesos o tareas institucionales.', 30],
        ] as [$categoryCode, $code, $name, $description, $sortOrder]) {
            $category = $categories->get($categoryCode);
            $subcategories->put($categoryCode.'-'.$code, TicketSubcategory::updateOrCreate(
                ['ticket_category_id' => $category->id, 'code' => $code],
                ['name' => $name, 'description' => $description, 'sort_order' => $sortOrder, 'is_active' => true],
            ));
        }

        return [$categories, $subcategories];
    }

    private function seedServiceLevels(): array
    {
        foreach ([
            ['Registrado', 'registrado', '#225C9A', true, false, 10],
            ['Pendiente de aprobación', 'pendiente-aprobacion', '#FFC107', false, false, 20],
            ['Asignado', 'asignado', '#225C9A', false, false, 30],
            ['En análisis', 'en-analisis', '#225C9A', false, false, 40],
            ['En proceso', 'en-proceso', '#FF9F0A', false, false, 50],
            ['Pendiente del usuario', 'pendiente-usuario', '#FFC107', false, false, 60],
            ['Solucionado', 'solucionado', '#007777', false, false, 70],
            ['Cerrado', 'cerrado', '#007777', false, true, 80],
            ['Cancelado', 'cancelado', '#888888', false, true, 90],
        ] as [$name, $slug, $color, $initial, $final, $sortOrder]) {
            TicketStatus::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'color' => $color, 'is_initial' => $initial, 'is_final' => $final,
                'is_active' => true, 'sort_order' => $sortOrder,
            ]);
        }

        $impacts = collect();
        foreach ([
            ['Bajo', 1, 'Afecta a una persona o tiene impacto operativo menor.'],
            ['Medio', 2, 'Afecta a un grupo, departamento o proceso relevante.'],
            ['Alto', 3, 'Afecta a varias áreas, un servicio crítico o a toda la institución.'],
        ] as [$name, $value, $description]) {
            $impacts->put($value, ImpactLevel::updateOrCreate(['value' => $value], ['name' => $name, 'description' => $description, 'is_active' => true]));
        }

        $urgencies = collect();
        foreach ([
            ['Baja', 1, 'Puede atenderse dentro de la planificación ordinaria.'],
            ['Media', 2, 'Requiere atención pronta para evitar afectación adicional.'],
            ['Alta', 3, 'Requiere atención inmediata por interrupción o riesgo significativo.'],
        ] as [$name, $value, $description]) {
            $urgencies->put($value, UrgencyLevel::updateOrCreate(['value' => $value], ['name' => $name, 'description' => $description, 'is_active' => true]));
        }

        $priorities = collect();
        foreach ([
            ['P4', 'Baja', '#888888', 1, 'Atención planificada sin impacto inmediato.'],
            ['P3', 'Media', '#225C9A', 2, 'Atención prioritaria dentro de la operación normal.'],
            ['P2', 'Alta', '#FF9F0A', 3, 'Afectación importante que requiere respuesta rápida.'],
            ['P1', 'Crítica', '#C62828', 4, 'Interrupción crítica o riesgo institucional significativo.'],
        ] as [$code, $name, $color, $weight, $description]) {
            $priorities->put($code, TicketPriority::updateOrCreate(['code' => $code], [
                'name' => $name, 'color' => $color, 'weight' => $weight,
                'description' => $description, 'is_active' => true,
            ]));
        }

        $matrix = [1 => [1 => 'P4', 2 => 'P4', 3 => 'P3'], 2 => [1 => 'P4', 2 => 'P3', 3 => 'P2'], 3 => [1 => 'P3', 2 => 'P2', 3 => 'P1']];
        foreach ($matrix as $impactValue => $urgencyRules) {
            foreach ($urgencyRules as $urgencyValue => $priorityCode) {
                PriorityMatrixRule::updateOrCreate([
                    'impact_level_id' => $impacts->get($impactValue)->id,
                    'urgency_level_id' => $urgencies->get($urgencyValue)->id,
                ], ['ticket_priority_id' => $priorities->get($priorityCode)->id]);
            }
        }

        foreach ([['P1', 15, 240, 120], ['P2', 30, 480, 300], ['P3', 120, 1440, 960], ['P4', 240, 2880, 1920]] as [$code, $response, $resolution, $escalation]) {
            ServiceLevelTarget::updateOrCreate([
                'ticket_priority_id' => $priorities->get($code)->id,
                'ticket_category_id' => null,
                'ticket_subcategory_id' => null,
            ], [
                'response_minutes' => $response, 'resolution_minutes' => $resolution,
                'escalation_minutes' => $escalation, 'business_hours_only' => true, 'is_active' => true,
            ]);
        }

        return [$priorities, $impacts, $urgencies];
    }

    private function seedForms($categories): void
    {
        $support = TicketForm::updateOrCreate(['code' => 'FRM-SOP-GEN'], [
            'ticket_category_id' => $categories->get('SOP')->id, 'ticket_subcategory_id' => null,
            'name' => 'Solicitud general de soporte', 'description' => 'Formulario base para asistencia técnica institucional.',
            'version' => 1, 'is_active' => true,
        ]);
        $this->seedFields($support, [
            ['asunto', 'Asunto', 'text', true, null, 10],
            ['descripcion', 'Descripción del problema', 'textarea', true, null, 20],
            ['usuarios_afectados', 'Cantidad de usuarios afectados', 'number', false, null, 30],
            ['evidencia', 'Evidencia', 'file', false, null, 40],
        ]);

        $development = TicketForm::updateOrCreate(['code' => 'FRM-DEV-REQ'], [
            'ticket_category_id' => $categories->get('DEV')->id, 'ticket_subcategory_id' => null,
            'name' => 'Solicitud de desarrollo', 'description' => 'Formulario para sistemas, mejoras, correcciones y automatizaciones.',
            'version' => 1, 'is_active' => true,
        ]);
        $this->seedFields($development, [
            ['necesidad_negocio', 'Necesidad institucional', 'textarea', true, null, 10],
            ['alcance', 'Alcance esperado', 'textarea', true, null, 20],
            ['clasificacion_informacion', 'Clasificación de la información', 'select', true, ['Pública', 'Uso interno', 'Confidencial', 'Restringida'], 30],
            ['requisitos_seguridad', 'Incluye requisitos de seguridad', 'checkbox', false, null, 40],
            ['fecha_esperada', 'Fecha esperada', 'date', false, null, 50],
        ]);
    }

    private function seedFields(TicketForm $form, array $fields): void
    {
        foreach ($fields as [$name, $label, $type, $required, $options, $order]) {
            FormField::updateOrCreate(['ticket_form_id' => $form->id, 'name' => $name], [
                'label' => $label, 'type' => $type, 'options' => $options, 'sort_order' => $order,
                'is_required' => $required, 'is_visible' => true, 'is_readonly' => false, 'is_active' => true,
            ]);
        }
    }

    private function seedApproval($categories): void
    {
        $workflow = ApprovalWorkflow::updateOrCreate(['code' => 'APR-DEV'], [
            'ticket_category_id' => $categories->get('DEV')->id, 'ticket_subcategory_id' => null,
            'requester_area_id' => null, 'minimum_priority_id' => null,
            'name' => 'Aprobación de solicitudes de desarrollo',
            'description' => 'Valida la necesidad solicitante y la viabilidad técnica antes de ser atendida.', 'is_active' => true,
        ]);
        ApprovalLevel::updateOrCreate(['approval_workflow_id' => $workflow->id, 'level_number' => 1], [
            'name' => 'Aprobación de jefatura solicitante', 'approver_type' => 'requester_area_head',
            'is_required' => true, 'allow_delegation' => true, 'is_active' => true,
        ]);
        ApprovalLevel::updateOrCreate(['approval_workflow_id' => $workflow->id, 'level_number' => 2], [
            'name' => 'Validación de Jefatura de IT', 'approver_type' => 'role',
            'approver_role_id' => Role::where('slug', 'jefatura-it')->value('id'),
            'is_required' => true, 'allow_delegation' => true, 'is_active' => true,
        ]);
    }

    private function seedRouting($categories): void
    {
        $itAreaId = Area::where('code', 'IT')->value('id');
        foreach ([
            ['SOP', 'GRP-SOP', 'Mesa de Ayuda'], ['INC', 'GRP-INF', 'Atención de incidentes'],
            ['REQ', 'GRP-SOP', 'Requerimientos de IT'], ['DEV', 'GRP-DEV', 'Solicitudes de desarrollo'],
        ] as [$categoryCode, $groupCode, $name]) {
            RoutingRule::updateOrCreate(['name' => $name], [
                'ticket_category_id' => $categories->get($categoryCode)->id, 'ticket_subcategory_id' => null,
                'it_area_id' => $itAreaId, 'it_group_id' => TechnicalGroup::where('code', $groupCode)->value('id'),
                'assignee_user_id' => null, 'precedence' => 100, 'is_active' => true,
            ]);
        }
    }
}
