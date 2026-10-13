<?php

namespace Tests\Feature;

use App\Http\Controllers\ReportController;
use App\Models\SystemAlert;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OperationalAlertService;
use App\Services\SystemNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleEightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_operational_workbench_is_scoped_and_protected(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $technician = $this->user('aplicaciones@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $supportTicket = Ticket::where('number', 'like', 'ZT-%')->where('current_assignee_id', $this->user('soporte@zolicguate.com')->id)->firstOrFail();

        $this->actingAs($admin)->get(route('operations.index'))->assertOk()->assertSee('Bandeja técnica de IT')->assertSee($supportTicket->number);
        $this->actingAs($technician)->get(route('operations.index'))->assertOk()->assertDontSee($supportTicket->number);
        $this->actingAs($requester)->get(route('operations.index'))->assertForbidden();
        $this->assertDatabaseHas('ticket_statuses', ['slug' => 'pendiente-tercero', 'is_active' => true]);
    }

    public function test_third_party_dependency_pauses_and_resumes_ticket_with_notification(): void
    {
        $technician = $this->user('soporte@zolicguate.com');
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $ticket = Ticket::where('number', 'like', 'ZT-%')->whereHas('status', fn ($query) => $query->where('slug', 'en-proceso'))->firstOrFail();

        $this->actingAs($technician)->post(route('tickets.third-parties.store', $ticket), [
            'provider' => 'Proveedor de telecomunicaciones',
            'reference' => 'CASO-8842',
            'description' => 'Se requiere validación del enlace por parte del proveedor externo.',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $dependency = $ticket->thirdPartyDependencies()->firstOrFail();
        $this->assertSame('pendiente-tercero', $ticket->fresh()->status->slug);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $requester->id,
            'notifiable_type' => User::class,
        ]);

        $this->actingAs($technician)->patch(route('tickets.third-parties.resolve', [$ticket, $dependency]), [
            'resolution' => 'El proveedor confirmó la estabilidad del enlace y autorizó continuar las pruebas.',
        ])->assertRedirect();
        $this->assertSame('resolved', $dependency->fresh()->status);
        $this->assertSame('en-proceso', $ticket->fresh()->status->slug);
    }

    public function test_manual_escalation_notifies_it_management_and_is_audited(): void
    {
        $technician = $this->user('soporte@zolicguate.com');
        $head = $this->user('jefatura.it@zolicguate.com');
        $ticket = Ticket::where('number', 'like', 'ZT-%')->where('current_assignee_id', $technician->id)->firstOrFail();

        $this->actingAs($technician)->post(route('tickets.escalations.store', $ticket), [
            'level' => 'area_manager',
            'reason' => 'Se necesita autorización y coordinación superior para continuar la atención.',
        ])->assertRedirect();

        $this->assertDatabaseHas('ticket_escalations', [
            'ticket_id' => $ticket->id, 'level' => 'area_manager',
            'escalated_to_user_id' => $head->id, 'source' => 'manual',
        ]);
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'event' => 'ticket_escalated']);
        $this->assertTrue($head->notifications()->get()->contains(fn ($item) => data_get($item->data, 'event') === 'ticket_escalated'));
    }

    public function test_notification_center_only_manages_the_authenticated_users_notifications(): void
    {
        $requester = $this->user('usuario.ticket@zolicguate.com');
        $technician = $this->user('soporte@zolicguate.com');
        app(SystemNotificationService::class)->sendToUsers([$requester], [
            'category' => 'ticket', 'severity' => 'info', 'title' => 'Aviso de prueba',
            'message' => 'Notificación institucional de prueba.', 'url' => route('tickets.index'), 'event' => 'test_notice',
        ]);
        $notification = $requester->unreadNotifications()->firstOrFail();

        $this->actingAs($requester)->get(route('notifications.index'))->assertOk()->assertSee('Aviso de prueba');
        $this->actingAs($technician)->patch(route('notifications.read', $notification))->assertNotFound();
        $this->actingAs($requester)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_sla_alert_generation_is_idempotent_and_escalates_overdue_ticket(): void
    {
        Ticket::whereNull('solved_at')->update(['response_due_at' => now()->addDays(2), 'resolution_due_at' => now()->addDays(3)]);
        $ticket = Ticket::where('number', 'like', 'ZT-%')->whereNotNull('current_assignee_id')->firstOrFail();
        $ticket->update(['first_response_at' => null, 'response_due_at' => now()->subMinute(), 'resolution_due_at' => now()->addDays(3)]);

        $service = app(OperationalAlertService::class);
        $this->assertSame(1, $service->process());
        $this->assertSame(0, $service->process());
        $this->assertDatabaseHas('system_alerts', ['ticket_id' => $ticket->id, 'type' => 'response_overdue', 'severity' => 'critical']);
        $this->assertDatabaseHas('ticket_escalations', ['ticket_id' => $ticket->id, 'source' => 'automatic', 'level' => 'area_manager']);
        $this->assertSame(1, SystemAlert::where('ticket_id', $ticket->id)->where('type', 'response_overdue')->count());
    }

    public function test_reports_render_and_csv_export_neutralizes_formula_injection(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $ticket = Ticket::where('number', 'like', 'ZT-%')->firstOrFail();
        $ticket->update(['title' => '=HYPERLINK("https://invalid.example")', 'created_at' => now()]);
        $query = ['report' => 'tickets', 'start_date' => today()->subDay()->toDateString(), 'end_date' => today()->toDateString()];

        $this->actingAs($admin)->get(route('reports.index', $query))->assertOk()->assertSee('Inteligencia operativa')->assertSee($ticket->number);
        $response = $this->actingAs($admin)->get(route('reports.export', $query))->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_technician_cannot_export_or_open_privileged_inventory_reports(): void
    {
        $technician = $this->user('soporte@zolicguate.com');
        $dates = ['start_date' => today()->subDay()->toDateString(), 'end_date' => today()->toDateString()];

        $this->actingAs($technician)->get(route('reports.index', $dates + ['report' => 'equipment']))->assertForbidden();
        $this->actingAs($technician)->get(route('reports.export', $dates + ['report' => 'tickets']))->assertForbidden();
        $this->actingAs($technician)->get(route('reports.index', $dates + ['report' => 'tickets']))->assertOk();
    }

    public function test_every_report_family_renders_for_authorized_management(): void
    {
        $admin = $this->user('admin@zolicguate.com');
        $dates = ['start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->toDateString()];

        foreach (array_keys(ReportController::TYPES) as $type) {
            $this->actingAs($admin)->get(route('reports.index', $dates + ['report' => $type]))
                ->assertOk()->assertSee(ReportController::TYPES[$type]);
        }
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }
}
