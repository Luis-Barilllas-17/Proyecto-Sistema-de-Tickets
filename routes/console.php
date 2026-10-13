<?php

use App\Services\MaintenanceService;
use App\Services\OperationalAlertService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maintenance:generate-orders', function (MaintenanceService $maintenance) {
    $orders = $maintenance->generateDueOrders();
    $this->info($orders->count().' orden(es) preventiva(s) generada(s).');
})->purpose('Generate preventive maintenance work orders and linked tickets');

Schedule::command('maintenance:generate-orders')->dailyAt('06:00')->withoutOverlapping();

Artisan::command('operations:process-alerts', function (OperationalAlertService $alerts) {
    $count = $alerts->process();
    $this->info($count.' alerta(s) nueva(s) generada(s).');
})->purpose('Generate SLA, third-party and maintenance alerts and escalations');

Schedule::command('operations:process-alerts')->everyFiveMinutes()->withoutOverlapping();
