<?php

use App\Http\Controllers\AdministrationDashboardController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ConfigurationDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevelopmentAccessProfileController;
use App\Http\Controllers\DevelopmentController;
use App\Http\Controllers\DevelopmentDeploymentController;
use App\Http\Controllers\DevelopmentRequirementController;
use App\Http\Controllers\DevelopmentSecurityController;
use App\Http\Controllers\DevelopmentTestController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentEventController;
use App\Http\Controllers\EquipmentLicenseController;
use App\Http\Controllers\EquipmentMovementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MaintenanceDashboardController;
use App\Http\Controllers\MaintenanceOrderController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OperationsDashboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoutingRuleController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\ServiceLevelController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplyMovementController;
use App\Http\Controllers\TechnicalGroupController;
use App\Http\Controllers\TicketApprovalController;
use App\Http\Controllers\TicketAssignmentController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketCategoryController;
use App\Http\Controllers\TicketCollaborationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketEscalationController;
use App\Http\Controllers\TicketFormController;
use App\Http\Controllers\TicketSupplyUsageController;
use App\Http\Controllers\TicketThirdPartyController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/recuperar-contrasena', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active', 'session.timeout'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/cambiar-contrasena', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/cambiar-contrasena', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/panel', DashboardController::class)->name('dashboard');
        Route::delete('/sesiones/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

        Route::get('/operacion', OperationsDashboardController::class)
            ->middleware('permission:operations.workbench')->name('operations.index');
        Route::prefix('notificaciones')->name('notifications.')->middleware('permission:notifications.view')->group(function (): void {
            Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
            Route::patch('/leer-todas', [NotificationCenterController::class, 'readAll'])->name('read-all');
            Route::patch('/{notification}', [NotificationCenterController::class, 'read'])->name('read');
        });
        Route::prefix('reportes')->name('reports.')->middleware('permission:reports.view')->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/exportar', [ReportController::class, 'export'])->middleware('permission:reports.export')->name('export');
        });

        Route::prefix('seguridad')->name('security.')->group(function (): void {
            Route::get('/', [SecurityController::class, 'index'])->middleware('permission:security.view')->name('index');
            Route::get('/usuarios/{user}/contrasena', [SecurityController::class, 'editPassword'])->middleware('permission:users.reset_password')->name('password.edit');
            Route::put('/usuarios/{user}/contrasena', [SecurityController::class, 'updatePassword'])->middleware('permission:users.reset_password')->name('password.update');
            Route::patch('/usuarios/{user}/estado', [SecurityController::class, 'updateStatus'])->middleware('permission:users.status')->name('users.status');
            Route::patch('/usuarios/{user}/desbloqueo', [SecurityController::class, 'unlock'])->middleware('permission:security.manage')->name('users.unlock');
            Route::delete('/sesiones/{session}', [SecurityController::class, 'destroySession'])->middleware('permission:security.manage')->name('sessions.destroy');
        });

        Route::prefix('administracion')->name('administration.')->group(function (): void {
            Route::get('/', AdministrationDashboardController::class)->middleware('permission:users.view')->name('dashboard');

            Route::get('/usuarios', [UserManagementController::class, 'index'])->middleware('permission:users.view')->name('users.index');
            Route::get('/usuarios/crear', [UserManagementController::class, 'create'])->middleware('permission:users.create')->name('users.create');
            Route::post('/usuarios', [UserManagementController::class, 'store'])->middleware('permission:users.create')->name('users.store');
            Route::get('/usuarios/{user}/editar', [UserManagementController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
            Route::put('/usuarios/{user}', [UserManagementController::class, 'update'])->middleware('permission:users.update')->name('users.update');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
            Route::get('/roles/crear', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
            Route::get('/roles/{role}/editar', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');

            Route::get('/organizacion', [OrganizationController::class, 'index'])->middleware('permission:organization.view')->name('organization.index');
            Route::post('/organizacion/areas', [OrganizationController::class, 'storeArea'])->middleware('permission:organization.create')->name('areas.store');
            Route::put('/organizacion/areas/{area}', [OrganizationController::class, 'updateArea'])->middleware('permission:organization.update')->name('areas.update');
            Route::post('/organizacion/departamentos', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.create')->name('departments.store');
            Route::put('/organizacion/departamentos/{department}', [OrganizationController::class, 'updateDepartment'])->middleware('permission:organization.update')->name('departments.update');

            Route::get('/grupos-tecnicos', [TechnicalGroupController::class, 'index'])->middleware('permission:groups.view')->name('groups.index');
            Route::get('/grupos-tecnicos/crear', [TechnicalGroupController::class, 'create'])->middleware('permission:groups.create')->name('groups.create');
            Route::post('/grupos-tecnicos', [TechnicalGroupController::class, 'store'])->middleware('permission:groups.create')->name('groups.store');
            Route::get('/grupos-tecnicos/{group}/editar', [TechnicalGroupController::class, 'edit'])->middleware('permission:groups.update')->name('groups.edit');
            Route::put('/grupos-tecnicos/{group}', [TechnicalGroupController::class, 'update'])->middleware('permission:groups.update')->name('groups.update');
            Route::post('/especialidades', [TechnicalGroupController::class, 'storeSpecialty'])->middleware('permission:specialties.manage')->name('specialties.store');
            Route::put('/especialidades/{specialty}', [TechnicalGroupController::class, 'updateSpecialty'])->middleware('permission:specialties.manage')->name('specialties.update');
        });

        Route::prefix('configuracion')->name('configuration.')->group(function (): void {
            Route::get('/', ConfigurationDashboardController::class)->middleware('permission:configuration.view')->name('dashboard');

            Route::get('/categorias', [TicketCategoryController::class, 'index'])->middleware('permission:configuration.view')->name('categories.index');
            Route::post('/categorias', [TicketCategoryController::class, 'storeCategory'])->middleware('permission:categories.manage')->name('categories.store');
            Route::put('/categorias/{category}', [TicketCategoryController::class, 'updateCategory'])->middleware('permission:categories.manage')->name('categories.update');
            Route::post('/subcategorias', [TicketCategoryController::class, 'storeSubcategory'])->middleware('permission:categories.manage')->name('subcategories.store');
            Route::put('/subcategorias/{subcategory}', [TicketCategoryController::class, 'updateSubcategory'])->middleware('permission:categories.manage')->name('subcategories.update');

            Route::get('/formularios', [TicketFormController::class, 'index'])->middleware('permission:configuration.view')->name('forms.index');
            Route::get('/formularios/crear', [TicketFormController::class, 'create'])->middleware('permission:forms.manage')->name('forms.create');
            Route::post('/formularios', [TicketFormController::class, 'store'])->middleware('permission:forms.manage')->name('forms.store');
            Route::get('/formularios/{form}/editar', [TicketFormController::class, 'edit'])->middleware('permission:forms.manage')->name('forms.edit');
            Route::put('/formularios/{form}', [TicketFormController::class, 'update'])->middleware('permission:forms.manage')->name('forms.update');
            Route::post('/formularios/{form}/campos', [TicketFormController::class, 'storeField'])->middleware('permission:forms.manage')->name('fields.store');
            Route::put('/formularios/{form}/campos/{field}', [TicketFormController::class, 'updateField'])->middleware('permission:forms.manage')->name('fields.update');

            Route::get('/aprobaciones', [ApprovalWorkflowController::class, 'index'])->middleware('permission:configuration.view')->name('approvals.index');
            Route::get('/aprobaciones/crear', [ApprovalWorkflowController::class, 'create'])->middleware('permission:approvals.manage')->name('approvals.create');
            Route::post('/aprobaciones', [ApprovalWorkflowController::class, 'store'])->middleware('permission:approvals.manage')->name('approvals.store');
            Route::get('/aprobaciones/{workflow}/editar', [ApprovalWorkflowController::class, 'edit'])->middleware('permission:approvals.manage')->name('approvals.edit');
            Route::put('/aprobaciones/{workflow}', [ApprovalWorkflowController::class, 'update'])->middleware('permission:approvals.manage')->name('approvals.update');
            Route::post('/aprobaciones/{workflow}/niveles', [ApprovalWorkflowController::class, 'storeLevel'])->middleware('permission:approvals.manage')->name('approval-levels.store');
            Route::put('/aprobaciones/{workflow}/niveles/{level}', [ApprovalWorkflowController::class, 'updateLevel'])->middleware('permission:approvals.manage')->name('approval-levels.update');
            Route::post('/delegaciones', [ApprovalWorkflowController::class, 'storeDelegation'])->middleware('permission:approvals.manage')->name('delegations.store');
            Route::put('/delegaciones/{delegation}', [ApprovalWorkflowController::class, 'updateDelegation'])->middleware('permission:approvals.manage')->name('delegations.update');

            Route::get('/direccionamiento', [RoutingRuleController::class, 'index'])->middleware('permission:configuration.view')->name('routing.index');
            Route::post('/direccionamiento', [RoutingRuleController::class, 'store'])->middleware('permission:routing.manage')->name('routing.store');
            Route::put('/direccionamiento/{routingRule}', [RoutingRuleController::class, 'update'])->middleware('permission:routing.manage')->name('routing.update');

            Route::get('/niveles-servicio', [ServiceLevelController::class, 'index'])->middleware('permission:configuration.view')->name('service-levels.index');
            Route::post('/estados', [ServiceLevelController::class, 'storeStatus'])->middleware('permission:service_levels.manage')->name('statuses.store');
            Route::put('/estados/{status}', [ServiceLevelController::class, 'updateStatus'])->middleware('permission:service_levels.manage')->name('statuses.update');
            Route::post('/impactos', [ServiceLevelController::class, 'storeImpact'])->middleware('permission:service_levels.manage')->name('impacts.store');
            Route::put('/impactos/{impact}', [ServiceLevelController::class, 'updateImpact'])->middleware('permission:service_levels.manage')->name('impacts.update');
            Route::post('/urgencias', [ServiceLevelController::class, 'storeUrgency'])->middleware('permission:service_levels.manage')->name('urgencies.store');
            Route::put('/urgencias/{urgency}', [ServiceLevelController::class, 'updateUrgency'])->middleware('permission:service_levels.manage')->name('urgencies.update');
            Route::post('/prioridades', [ServiceLevelController::class, 'storePriority'])->middleware('permission:service_levels.manage')->name('priorities.store');
            Route::put('/prioridades/{priority}', [ServiceLevelController::class, 'updatePriority'])->middleware('permission:service_levels.manage')->name('priorities.update');
            Route::put('/matriz-prioridad', [ServiceLevelController::class, 'updateMatrix'])->middleware('permission:service_levels.manage')->name('priority-matrix.update');
            Route::post('/objetivos-sla', [ServiceLevelController::class, 'storeTarget'])->middleware('permission:service_levels.manage')->name('sla-targets.store');
            Route::put('/objetivos-sla/{target}', [ServiceLevelController::class, 'updateTarget'])->middleware('permission:service_levels.manage')->name('sla-targets.update');
        });

        Route::prefix('tickets')->name('tickets.')->group(function (): void {
            Route::get('/', [TicketController::class, 'index'])->middleware('permission:tickets.view')->name('index');
            Route::get('/crear', [TicketController::class, 'create'])->middleware('permission:tickets.create')->name('create');
            Route::post('/', [TicketController::class, 'store'])->middleware('permission:tickets.create')->name('store');

            Route::get('/{ticket}', [TicketController::class, 'show'])->middleware('permission:tickets.view')->name('show');
            Route::get('/{ticket}/editar', [TicketController::class, 'edit'])->middleware('permission:tickets.create')->name('edit');
            Route::put('/{ticket}', [TicketController::class, 'update'])->middleware('permission:tickets.create')->name('update');
            Route::patch('/{ticket}/estado', [TicketController::class, 'updateStatus'])->middleware('permission:tickets.status')->name('status');
            Route::patch('/{ticket}/cancelar', [TicketController::class, 'cancel'])->middleware('permission:tickets.view')->name('cancel');
            Route::patch('/{ticket}/reabrir', [TicketController::class, 'reopen'])->middleware('permission:tickets.reopen')->name('reopen');

            Route::post('/{ticket}/aprobaciones/{approval}/decision', [TicketApprovalController::class, 'decide'])
                ->middleware('permission:tickets.approve')->name('approvals.decide');
            Route::post('/{ticket}/asignacion', [TicketAssignmentController::class, 'store'])->middleware('permission:tickets.assign')->name('assignments.store');
            Route::post('/{ticket}/transferencia', [TicketAssignmentController::class, 'transfer'])->middleware('permission:tickets.transfer')->name('assignments.transfer');
            Route::patch('/{ticket}/asignaciones/{assignment}/respuesta', [TicketAssignmentController::class, 'respond'])
                ->middleware('permission:tickets.accept_assignment')->name('assignments.respond');

            Route::post('/{ticket}/comentarios', [TicketCollaborationController::class, 'comment'])->middleware('permission:tickets.comment')->name('comments.store');
            Route::post('/{ticket}/notas-internas', [TicketCollaborationController::class, 'internalNote'])->middleware('permission:tickets.internal_note')->name('notes.store');
            Route::post('/{ticket}/actividades', [TicketCollaborationController::class, 'activity'])->middleware('permission:tickets.activity')->name('activities.store');
            Route::post('/{ticket}/relaciones', [TicketCollaborationController::class, 'relate'])->middleware('permission:tickets.relate')->name('relations.store');
            Route::post('/{ticket}/solucion', [TicketCollaborationController::class, 'solve'])->middleware('permission:tickets.solve')->name('solution.store');
            Route::post('/{ticket}/revision', [TicketCollaborationController::class, 'review'])->middleware('permission:tickets.review')->name('review.store');
            Route::post('/{ticket}/escalamientos', [TicketEscalationController::class, 'store'])->middleware('permission:tickets.escalate')->name('escalations.store');
            Route::post('/{ticket}/terceros', [TicketThirdPartyController::class, 'store'])->middleware('permission:tickets.third_party')->name('third-parties.store');
            Route::patch('/{ticket}/terceros/{dependency}', [TicketThirdPartyController::class, 'resolve'])->middleware('permission:tickets.third_party')->name('third-parties.resolve');

            Route::get('/{ticket}/archivos/{attachment}', [TicketAttachmentController::class, 'download'])->name('attachments.download');
            Route::delete('/{ticket}/archivos/{attachment}', [TicketAttachmentController::class, 'destroy'])->name('attachments.destroy');
            Route::post('/{ticket}/insumos', [TicketSupplyUsageController::class, 'store'])->middleware('permission:inventory.consume')->name('supplies.store');
            Route::delete('/{ticket}/insumos/{usage}', [TicketSupplyUsageController::class, 'destroy'])->middleware('permission:inventory.consume')->name('supplies.destroy');
        });

        Route::prefix('desarrollo')->name('development.')->group(function (): void {
            Route::get('/', [DevelopmentController::class, 'index'])->middleware('permission:development.view')->name('index');
            Route::get('/crear', [DevelopmentController::class, 'create'])->middleware('permission:development.create')->name('create');
            Route::post('/', [DevelopmentController::class, 'store'])->middleware('permission:development.create')->name('store');
            Route::get('/{developmentRequest}', [DevelopmentController::class, 'show'])->middleware('permission:development.view')->name('show');
            Route::get('/{developmentRequest}/editar', [DevelopmentController::class, 'edit'])->middleware('permission:development.view')->name('edit');
            Route::put('/{developmentRequest}', [DevelopmentController::class, 'update'])->middleware('permission:development.view')->name('update');

            Route::post('/{developmentRequest}/requerimientos', [DevelopmentRequirementController::class, 'store'])
                ->middleware('permission:development.requirements')->name('requirements.store');
            Route::put('/{developmentRequest}/requerimientos/{requirement}', [DevelopmentRequirementController::class, 'update'])
                ->middleware('permission:development.requirements')->name('requirements.update');
            Route::put('/{developmentRequest}/seguridad', [DevelopmentSecurityController::class, 'update'])
                ->middleware('permission:development.security_manage')->name('security.update');
            Route::post('/{developmentRequest}/perfiles-acceso', [DevelopmentAccessProfileController::class, 'store'])
                ->middleware('permission:development.security_manage')->name('access-profiles.store');
            Route::post('/{developmentRequest}/pruebas', [DevelopmentTestController::class, 'store'])
                ->middleware('permission:development.tests')->name('tests.store');
            Route::post('/{developmentRequest}/despliegues', [DevelopmentDeploymentController::class, 'store'])
                ->middleware('permission:development.deploy_request')->name('deployments.store');
            Route::patch('/{developmentRequest}/despliegues/{authorization}/decision', [DevelopmentDeploymentController::class, 'decide'])
                ->middleware('permission:development.deploy_authorize')->name('deployments.decide');
        });

        Route::prefix('inventario')->name('inventory.')->group(function (): void {
            Route::get('/', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('index');
            Route::get('/equipos/crear', [EquipmentController::class, 'create'])->middleware('permission:inventory.equipment_manage')->name('equipment.create');
            Route::post('/equipos', [EquipmentController::class, 'store'])->middleware('permission:inventory.equipment_manage')->name('equipment.store');
            Route::get('/equipos/{equipment}', [EquipmentController::class, 'show'])->middleware('permission:inventory.view')->name('equipment.show');
            Route::get('/equipos/{equipment}/editar', [EquipmentController::class, 'edit'])->middleware('permission:inventory.equipment_manage')->name('equipment.edit');
            Route::put('/equipos/{equipment}', [EquipmentController::class, 'update'])->middleware('permission:inventory.equipment_manage')->name('equipment.update');
            Route::get('/equipos/{equipment}/etiqueta', [EquipmentController::class, 'label'])->middleware('permission:inventory.view')->name('equipment.label');
            Route::post('/equipos/{equipment}/movimientos', [EquipmentMovementController::class, 'store'])->middleware('permission:inventory.assignment_manage')->name('equipment.movements.store');
            Route::post('/equipos/{equipment}/historial', [EquipmentEventController::class, 'store'])->middleware('permission:inventory.technical_history')->name('equipment.events.store');
            Route::post('/equipos/{equipment}/licencias', [EquipmentLicenseController::class, 'store'])->middleware('permission:inventory.equipment_manage')->name('equipment.licenses.store');
            Route::delete('/equipos/{equipment}/licencias/{license}', [EquipmentLicenseController::class, 'destroy'])->middleware('permission:inventory.equipment_manage')->name('equipment.licenses.destroy');

            Route::get('/insumos', [SupplyController::class, 'index'])->middleware('permission:inventory.view')->name('supplies.index');
            Route::get('/insumos/crear', [SupplyController::class, 'create'])->middleware('permission:inventory.stock_manage')->name('supplies.create');
            Route::post('/insumos', [SupplyController::class, 'store'])->middleware('permission:inventory.stock_manage')->name('supplies.store');
            Route::get('/insumos/{supply}', [SupplyController::class, 'show'])->middleware('permission:inventory.view')->name('supplies.show');
            Route::get('/insumos/{supply}/editar', [SupplyController::class, 'edit'])->middleware('permission:inventory.stock_manage')->name('supplies.edit');
            Route::put('/insumos/{supply}', [SupplyController::class, 'update'])->middleware('permission:inventory.stock_manage')->name('supplies.update');
            Route::post('/insumos/{supply}/movimientos', [SupplyMovementController::class, 'store'])->middleware('permission:inventory.stock_manage')->name('supplies.movements.store');
        });

        Route::prefix('mantenimiento')->name('maintenance.')->group(function (): void {
            Route::get('/', MaintenanceDashboardController::class)->middleware('permission:maintenance.view')->name('index');
            Route::post('/generar-ordenes', [MaintenanceOrderController::class, 'generate'])->middleware('permission:maintenance.generate_orders')->name('orders.generate');

            Route::get('/planes/crear', [MaintenancePlanController::class, 'create'])->middleware('permission:maintenance.manage_plans')->name('plans.create');
            Route::post('/planes', [MaintenancePlanController::class, 'store'])->middleware('permission:maintenance.manage_plans')->name('plans.store');
            Route::get('/planes/{plan}', [MaintenancePlanController::class, 'show'])->middleware('permission:maintenance.view')->name('plans.show');
            Route::get('/planes/{plan}/editar', [MaintenancePlanController::class, 'edit'])->middleware('permission:maintenance.manage_plans')->name('plans.edit');
            Route::put('/planes/{plan}', [MaintenancePlanController::class, 'update'])->middleware('permission:maintenance.manage_plans')->name('plans.update');
            Route::patch('/planes/{plan}/equipos/{assignment}', [MaintenancePlanController::class, 'reschedule'])->middleware('permission:maintenance.schedule')->name('plans.assignments.update');
            Route::post('/planes/{plan}/equipos/{assignment}/orden', [MaintenanceOrderController::class, 'generateAssignment'])->middleware('permission:maintenance.generate_orders')->name('plans.assignments.generate');

            Route::get('/ordenes/{order}', [MaintenanceOrderController::class, 'show'])->middleware('permission:maintenance.view')->name('orders.show');
            Route::post('/ordenes/{order}/iniciar', [MaintenanceOrderController::class, 'start'])->middleware('permission:maintenance.execute')->name('orders.start');
            Route::post('/ordenes/{order}/completar', [MaintenanceOrderController::class, 'complete'])->middleware('permission:maintenance.execute')->name('orders.complete');
            Route::get('/ordenes/{order}/evidencias/{evidence}', [MaintenanceOrderController::class, 'download'])->middleware('permission:maintenance.view')->name('orders.evidences.download');
        });
    });
});
