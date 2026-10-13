<?php

namespace Tests\Feature;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentTestEvidence;
use App\Models\ImpactLevel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TicketCategory;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleFiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_requester_can_open_module_and_save_a_specialized_draft(): void
    {
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $subcategory = $this->developmentSubcategory();

        $this->actingAs($requester)->get(route('development.index'))->assertOk();
        $this->actingAs($requester)->get(route('development.create'))->assertOk();
        $this->actingAs($requester)->post(route('development.store'), [
            'action' => 'draft',
            'request_type' => 'improvement',
            'ticket_subcategory_id' => $subcategory->id,
            'security_controls' => ['authentication', 'input_validation'],
        ])->assertRedirect();

        $request = DevelopmentRequest::latest('id')->firstOrFail();
        $this->assertTrue($request->ticket->is_draft);
        $this->assertSame('development', $request->ticket->attention_type);
        $this->assertSame('DEV', $request->ticket->category->code);
        $this->assertCount(6, $request->securityControls);
        $this->assertSame(2, $request->securityControls->where('is_required', true)->count());
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $request->ticket_id,
            'event' => 'development_request_created',
        ]);
    }

    public function test_submission_enforces_classification_details_and_rejects_a_foreign_subcategory(): void
    {
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $payload = $this->completePayload();

        $this->actingAs($requester)->post(route('development.store'), array_replace($payload, [
            'contains_sensitive_data' => 1,
            'sensitive_data_description' => null,
        ]))->assertSessionHasErrors('sensitive_data_description');

        $foreignSubcategory = TicketSubcategory::whereHas('category', fn ($query) => $query->where('code', 'SOP'))->firstOrFail();
        $this->actingAs($requester)->post(route('development.store'), array_replace($payload, [
            'ticket_subcategory_id' => $foreignSubcategory->id,
        ]))->assertNotFound();

        $this->actingAs($requester)->post(route('development.store'), $payload)->assertRedirect();
        $request = DevelopmentRequest::latest('id')->firstOrFail();

        $this->assertFalse($request->ticket->is_draft);
        $this->assertSame('pending', $request->ticket->approval_status);
        $this->assertSame(2, $request->ticket->approvals()->count());
        $this->assertSame('confidential', $request->information_classification);
        $this->assertTrue($request->contains_sensitive_data);
    }

    public function test_ticket_scope_prevents_another_requester_from_reading_the_development_record(): void
    {
        $developmentRequest = $this->demoRequest();
        $stranger = User::factory()->create(['email_verified_at' => now(), 'status' => 'active']);
        $this->givePermissions($stranger, ['tickets.view', 'development.view']);

        $this->actingAs($stranger)->get(route('development.show', $developmentRequest))->assertForbidden();
        $this->actingAs($developmentRequest->ticket->requester)
            ->get(route('development.show', $developmentRequest))->assertOk();
    }

    public function test_requirements_access_profiles_and_security_verification_are_audited_and_authorized(): void
    {
        $developmentRequest = $this->demoRequest();
        $developer = $this->user('aplicaciones@zolicguate.com');
        $itHead = $this->user('jefatura.it@zolicguate.com');

        $this->actingAs($developer)->post(route('development.requirements.store', $developmentRequest), [
            'type' => 'user_story',
            'title' => 'Consultar alertas del proceso',
            'description' => 'Como jefatura deseo consultar las alertas activas del proceso.',
            'acceptance_criteria' => 'Muestra únicamente alertas autorizadas y conserva la trazabilidad.',
            'priority' => 'high',
            'status' => 'approved',
            'sort_order' => 20,
        ])->assertRedirect();
        $this->assertDatabaseHas('development_requirements', [
            'development_request_id' => $developmentRequest->id,
            'code' => 'REQ-004',
            'status' => 'approved',
        ]);

        $this->actingAs($developer)->post(route('development.access-profiles.store', $developmentRequest), [
            'name' => 'Auditor institucional',
            'description' => 'Perfil de consulta controlada.',
            'permissions' => 'Consultar historial y exportar resultados autorizados.',
            'data_scope' => 'Registros de su área institucional.',
            'includes_sensitive_data' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('development_access_profiles', [
            'development_request_id' => $developmentRequest->id,
            'name' => 'Auditor institucional',
            'includes_sensitive_data' => true,
        ]);

        $control = $developmentRequest->securityControls()->where('control_type', 'authentication')->firstOrFail();
        $controlPayload = ['controls' => [$control->id => [
            'is_required' => 1,
            'requirement_details' => 'Usar sesión institucional y controles de bloqueo.',
            'implementation_notes' => 'Control implementado y sometido a revisión.',
            'status' => 'verified',
        ]]];

        $this->actingAs($developer)->put(route('development.security.update', $developmentRequest), $controlPayload)
            ->assertForbidden();
        $this->actingAs($itHead)->put(route('development.security.update', $developmentRequest), $controlPayload)
            ->assertRedirect();
        $this->assertDatabaseHas('development_security_controls', [
            'id' => $control->id,
            'status' => 'verified',
            'verified_by_id' => $itHead->id,
        ]);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $developmentRequest->ticket_id,
            'event' => 'development_security_updated',
        ]);
    }

    public function test_test_records_require_private_evidence_and_link_each_file_to_its_result(): void
    {
        Storage::fake('local');
        $developmentRequest = $this->demoRequest();
        $developer = $this->user('aplicaciones@zolicguate.com');

        $this->actingAs($developer)->post(route('development.tests.store', $developmentRequest), [
            'test_type' => 'security',
            'title' => 'Validación de controles OWASP',
            'environment' => 'QA',
            'result' => 'passed',
            'executed_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('attachments');

        foreach (array_keys(DevelopmentTestEvidence::TYPES) as $type) {
            $this->recordPassedEvidence($developmentRequest, $developer, $type);
        }

        $this->assertDatabaseCount('development_test_evidences', 4);
        $this->assertDatabaseCount('ticket_attachments', 4);
        foreach ($developmentRequest->testEvidences()->with('attachments')->get() as $evidence) {
            $attachment = $evidence->attachments->sole();
            $this->assertTrue($attachment->is_internal);
            $this->assertSame('development_test', $attachment->context);
            $this->assertSame($evidence->id, $attachment->development_test_evidence_id);
            $this->assertSame(64, strlen($attachment->sha256));
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_production_gate_requires_complete_controls_tests_and_jefatura_authorization(): void
    {
        Storage::fake('local');
        $developmentRequest = $this->demoRequest();
        $developer = $this->user('aplicaciones@zolicguate.com');
        $itHead = $this->user('jefatura.it@zolicguate.com');

        $deployment = [
            'version' => '2.4.0',
            'release_notes' => 'Incluye el flujo completo y los controles de seguridad definidos.',
            'deployment_plan' => 'Respaldar, publicar la versión, ejecutar migraciones y validar monitoreo.',
            'rollback_plan' => 'Restaurar la versión anterior, revertir migraciones y validar servicios.',
        ];
        $this->actingAs($developer)->post(route('development.deployments.store', $developmentRequest), $deployment)
            ->assertSessionHasErrors('deployment');

        $developmentRequest->requirements()->update(['status' => 'verified']);
        $developmentRequest->securityControls()->where('is_required', true)->update([
            'status' => 'verified',
            'verified_by_id' => $itHead->id,
            'verified_at' => now(),
        ]);
        foreach (array_keys(DevelopmentTestEvidence::TYPES) as $type) {
            $this->recordPassedEvidence($developmentRequest, $developer, $type);
        }

        $this->actingAs($developer)->post(route('development.deployments.store', $developmentRequest), $deployment)
            ->assertRedirect();
        $authorization = $developmentRequest->deploymentAuthorizations()->firstOrFail();
        $this->assertSame('pending', $authorization->status);

        $requiredControl = $developmentRequest->securityControls()->where('is_required', true)->firstOrFail();
        $requiredControl->update(['status' => 'implemented', 'verified_by_id' => null, 'verified_at' => null]);
        $this->actingAs($itHead)->patch(route('development.deployments.decide', [$developmentRequest, $authorization]), [
            'decision' => 'authorized',
            'decision_comment' => 'Se autoriza la publicación después de revisar el expediente técnico.',
        ])->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('development_deployment_authorizations', ['id' => $authorization->id, 'status' => 'pending']);

        $requiredControl->update(['status' => 'verified', 'verified_by_id' => $itHead->id, 'verified_at' => now()]);
        $this->actingAs($itHead)->patch(route('development.deployments.decide', [$developmentRequest, $authorization]), [
            'decision' => 'authorized',
            'decision_comment' => 'Expediente completo; se autoriza el despliegue institucional.',
        ])->assertRedirect();

        $this->assertDatabaseHas('development_deployment_authorizations', [
            'id' => $authorization->id,
            'status' => 'authorized',
            'decided_by_id' => $itHead->id,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $developmentRequest->ticket_id,
            'ticket_status_id' => TicketStatus::where('slug', 'cerrado')->value('id'),
        ]);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $developmentRequest->ticket_id,
            'event' => 'deployment_authorized',
        ]);
    }

    private function completePayload(): array
    {
        $requester = $this->user('usuario.ticket@zolicguate.com');

        return [
            'action' => 'submit',
            'request_type' => 'new_module',
            'ticket_subcategory_id' => $this->developmentSubcategory()->id,
            'project_name' => 'Portal seguro de coordinación institucional',
            'business_need' => 'Centralizar las solicitudes y conservar la trazabilidad de las decisiones.',
            'objective' => 'Crear un módulo con permisos por perfil y auditoría institucional.',
            'scope' => 'Análisis, construcción, pruebas, documentación y despliegue controlado.',
            'expected_benefits' => 'Menor tiempo de atención y mejor control de accesos.',
            'affected_system' => 'Sistema de gestión de tickets',
            'information_classification' => 'confidential',
            'contains_sensitive_data' => 1,
            'sensitive_data_description' => 'Identificadores institucionales, responsables y bitácora de acciones.',
            'involves_third_parties' => 0,
            'access_overview' => 'Solicitante, programador y jefatura autorizadora.',
            'requester_area_id' => $requester->area_id,
            'requester_department_id' => $requester->department_id,
            'location' => 'Edificio administrativo',
            'impact_level_id' => ImpactLevel::where('value', 2)->value('id'),
            'urgency_level_id' => UrgencyLevel::where('value', 2)->value('id'),
            'security_controls' => ['authentication', 'authorization', 'activity_logging', 'input_validation'],
        ];
    }

    private function recordPassedEvidence(DevelopmentRequest $developmentRequest, User $user, string $type): void
    {
        $this->actingAs($user)->post(route('development.tests.store', $developmentRequest), [
            'test_type' => $type,
            'title' => 'Evidencia aprobada '.$type,
            'environment' => 'QA institucional',
            'result' => 'passed',
            'executed_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'notes' => 'Resultado revisado y conforme con los criterios definidos.',
            'attachments' => [UploadedFile::fake()->create('evidencia-'.$type.'.pdf', 64, 'application/pdf')],
        ])->assertRedirect();
    }

    private function demoRequest(): DevelopmentRequest
    {
        return DevelopmentRequest::whereHas('ticket', fn ($query) => $query->where('number', sprintf('ZT-%d-%06d', now()->year, 4)))
            ->firstOrFail();
    }

    private function developmentSubcategory(): TicketSubcategory
    {
        $category = TicketCategory::where('code', 'DEV')->firstOrFail();

        return TicketSubcategory::where('ticket_category_id', $category->id)->where('is_active', true)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function givePermissions(User $user, array $slugs): void
    {
        $permissions = collect($slugs)->map(fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], [
            'module' => 'Pruebas', 'action' => $slug, 'name' => $slug, 'description' => $slug,
        ]));
        $role = Role::create([
            'name' => 'Perfil de prueba '.fake()->unique()->word(),
            'slug' => 'perfil-prueba-'.fake()->unique()->slug(),
            'is_active' => true,
        ]);
        $role->permissions()->attach($permissions->pluck('id'));
        $user->roles()->attach($role);
        $user->unsetRelation('roles');
    }
}
