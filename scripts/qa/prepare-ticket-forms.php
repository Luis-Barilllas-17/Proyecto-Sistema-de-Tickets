<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TicketFormDemoSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/ticket-environment.php';
$directory = ticketQaEnvironment();
if (! file_exists($directory.'/qa.sqlite')) {
    touch($directory.'/qa.sqlite');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => TicketFormDemoSeeder::class, '--force' => true]);
echo "Base SQLite de pruebas preparada: $directory/qa.sqlite\n";
echo "Cuentas: qa.tickets.{admin,tecnico,usuario}@zolicguate.com\n";
echo "Clave exclusiva de pruebas: PruebasTicket#2026\n";
