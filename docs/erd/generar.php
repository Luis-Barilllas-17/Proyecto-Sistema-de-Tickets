<?php

/**
 * Genera documentación del esquema sin iniciar Laravel ni cargar .env.
 * Las migraciones se ejecutan exclusivamente en SQLite :memory:, sin seeders.
 * Uso desde la raíz: php docs/erd/generar.php (requiere pdo_sqlite).
 */

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$root = dirname(__DIR__, 2);
$container = new Container;
$capsule = new Manager($container);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
]);
$container->instance('db', $capsule->getDatabaseManager());
$container->bind('db.schema', fn () => $capsule->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($container);

$connection = $capsule->getConnection();
if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Solo se permite una base SQLite en memoria.');
}

$migrations = glob($root.'/database/migrations/*.php');
sort($migrations);
$sources = [];
foreach ($migrations as $path) {
    $source = file_get_contents($path);
    preg_match_all("/Schema::(?:create|table)\('([^']+)'/", $source, $matches);
    foreach (array_unique($matches[1]) as $table) {
        $sources[$table][] = basename($path);
    }
    (require $path)->up();
}

$schema = [];
foreach (Schema::getTables() as $table) {
    $name = $table['name'];
    $schema[$name] = [
        'columns' => Schema::getColumns($name),
        'indexes' => array_values(Schema::getIndexes($name)),
        'foreign_keys' => Schema::getForeignKeys($name),
        'migrations' => $sources[$name] ?? [],
    ];
    usort($schema[$name]['foreign_keys'], fn ($a, $b) => $a['columns'] <=> $b['columns']);
}
ksort($schema);

foreach ($schema as $table => $definition) {
    foreach ($definition['foreign_keys'] as $fk) {
        if (! isset($schema[$fk['foreign_table']])) {
            throw new RuntimeException("Tabla de destino inexistente: {$table}");
        }
        foreach ($fk['columns'] as $column) {
            if (! in_array($column, array_column($definition['columns'], 'name'), true)) {
                throw new RuntimeException("Columna de origen inexistente: {$table}.{$column}");
            }
        }
        foreach ($fk['foreign_columns'] as $column) {
            if (! in_array($column, array_column($schema[$fk['foreign_table']]['columns'], 'name'), true)) {
                throw new RuntimeException("Columna de destino inexistente: {$column}");
            }
        }
    }
}

$groups = [
    '01-identidad' => ['Identidad, permisos y seguridad', [
        'users', 'roles', 'permissions', 'role_user', 'permission_role', 'security_events',
    ]],
    '02-organizacion' => ['Organización y equipos técnicos', [
        'areas', 'departments', 'it_groups', 'group_user', 'specialties', 'specialty_user',
    ]],
    '03-catalogos' => ['Clasificación, formularios y prioridades', [
        'ticket_categories', 'ticket_subcategories', 'ticket_forms', 'form_fields',
        'ticket_statuses', 'impact_levels', 'urgency_levels', 'ticket_priorities', 'priority_matrix_rules',
    ]],
    '04-reglas' => ['Aprobaciones, enrutamiento y niveles de servicio', [
        'approval_workflows', 'approval_levels', 'approval_delegations', 'routing_rules', 'service_level_targets',
    ]],
    '05-tickets' => ['Tickets: registro y asignación', [
        'tickets', 'ticket_field_values', 'ticket_approvals', 'ticket_assignments', 'ticket_sequences',
    ]],
    '06-seguimiento' => ['Tickets: colaboración y cierre', [
        'ticket_comments', 'ticket_activities', 'ticket_attachments', 'ticket_histories', 'ticket_relations', 'ticket_reviews',
    ]],
    '07-desarrollo' => ['Solicitudes de desarrollo', [
        'development_requests', 'development_requirements', 'development_security_controls',
        'development_access_profiles', 'development_test_evidences', 'development_deployment_authorizations',
    ]],
    '08-equipos' => ['Inventario de equipos', [
        'equipment', 'equipment_licenses', 'equipment_events', 'equipment_movements', 'ticket_equipment_links',
    ]],
    '09-suministros' => ['Suministros y consumos', [
        'supplies', 'ticket_supply_usages', 'supply_movements',
    ]],
    '10-mantenimiento' => ['Mantenimiento preventivo', [
        'maintenance_plans', 'maintenance_plan_equipment', 'maintenance_checklist_items', 'maintenance_sequences',
        'maintenance_orders', 'maintenance_order_items', 'maintenance_evidences',
    ]],
    '11-operacion' => ['Alertas, escalaciones y notificaciones', [
        'notifications', 'system_alerts', 'ticket_escalations', 'ticket_third_party_dependencies',
    ]],
    '12-infraestructura' => ['Infraestructura de Laravel', [
        'sessions', 'password_reset_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    ]],
];
$groupedTables = array_merge(...array_values(array_map(fn ($group) => $group[1], $groups)));
if (count($groupedTables) !== count(array_unique($groupedTables)) || array_diff(array_keys($schema), $groupedTables) || array_diff($groupedTables, array_keys($schema))) {
    throw new RuntimeException('Actualizar grupos: cada tabla debe pertenecer exactamente a un módulo.');
}

function primaryColumns(array $table): array
{
    foreach ($table['indexes'] as $index) {
        if ($index['primary']) {
            return $index['columns'];
        }
    }

    return [];
}

function isUnique(array $table, array $columns): bool
{
    sort($columns);
    foreach ($table['indexes'] as $index) {
        $indexed = $index['columns'];
        sort($indexed);
        if ($index['unique'] && $indexed === $columns) {
            return true;
        }
    }

    return false;
}

function columnKeys(array $table, string $column): array
{
    $keys = [];
    if (in_array($column, primaryColumns($table), true)) {
        $keys[] = 'PK';
    }
    foreach ($table['foreign_keys'] as $fk) {
        if (in_array($column, $fk['columns'], true)) {
            $keys[] = 'FK';
            break;
        }
    }
    if (isUnique($table, [$column]) && ! in_array('PK', $keys, true)) {
        $keys[] = 'UK';
    }

    return $keys;
}

function relationship(array $schema, string $child, array $fk): string
{
    $columns = array_column($schema[$child]['columns'], null, 'name');
    $optional = false;
    foreach ($fk['columns'] as $column) {
        $optional = $optional || $columns[$column]['nullable'];
    }
    $parentEnd = $optional ? '|o' : '||';
    $childEnd = isUnique($schema[$child], $fk['columns']) ? 'o|' : 'o{';
    $identifying = count(array_diff($fk['columns'], primaryColumns($schema[$child]))) === 0;
    $line = $identifying ? '--' : '..';

    return '    '.$fk['foreign_table'].' '.$parentEnd.$line.$childEnd.' '.$child.' : "'.implode(', ', $fk['columns']).'"';
}

function diagram(array $schema, array $owned, bool $allColumns = false): string
{
    $visible = array_fill_keys($owned, true);
    $edges = [];
    foreach ($owned as $name) {
        foreach ($schema[$name]['foreign_keys'] as $fk) {
            $visible[$fk['foreign_table']] = true;
            $edges[] = relationship($schema, $name, $fk);
        }
    }
    $lines = ['erDiagram', '    direction LR'];
    foreach (array_keys($visible) as $name) {
        $local = in_array($name, $owned, true);
        $lines[] = '    '.$name.' {';
        foreach ($schema[$name]['columns'] as $column) {
            $keys = columnKeys($schema[$name], $column['name']);
            $descriptive = in_array($column['name'], ['name', 'code', 'number', 'title', 'email', 'status', 'slug', 'label', 'type', 'notifiable_id', 'notifiable_type'], true);
            if (! $allColumns && ! ($local ? ($keys || $descriptive) : in_array('PK', $keys, true))) {
                continue;
            }
            $type = preg_replace('/[^a-zA-Z0-9_]/', '_', $column['type']);
            $nullable = $column['nullable'] && ! in_array('PK', $keys, true);
            $lines[] = '        '.$type.' '.$column['name'].($keys ? ' '.implode(', ', $keys) : '').' "'.($nullable ? 'NULL' : 'NOT NULL').'"';
        }
        $lines[] = '    }';
    }

    return implode("\n", array_merge($lines, $edges))."\n";
}

$foreignKeyCount = array_sum(array_map(fn ($table) => count($table['foreign_keys']), $schema));
$columnCount = array_sum(array_map(fn ($table) => count($table['columns']), $schema));
$metadata = [
    'source' => 'database/migrations; reconstrucción aislada, sin consultar la base de datos instalada',
    'engine' => 'SQLite :memory:; tipos normalizados por este motor',
    'table_count' => count($schema),
    'column_count' => $columnCount,
    'foreign_key_count' => $foreignKeyCount,
    'migration_sha256' => array_combine(array_map('basename', $migrations), array_map(fn ($file) => hash_file('sha256', $file), $migrations)),
];
file_put_contents(__DIR__.'/esquema.json', json_encode(['metadata' => $metadata, 'modules' => $groups, 'tables' => $schema], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
file_put_contents(__DIR__.'/completo.mmd', diagram($schema, array_keys($schema), true));

$md = [
    '# Diagrama entidad–relación completo — ZOLIC',
    '',
    'Un único diagrama con **'.count($schema).' tablas, '.$columnCount.' columnas y '.$foreignKeyCount.' claves foráneas** definidas en las migraciones del proyecto.',
    '',
    '[Abrir el diagrama con zoom](DIAGRAMA_ENTIDAD_RELACION.html) · [Imagen SVG completa](erd/completo.svg)',
    '',
    'PK: clave primaria; FK: clave foránea; UK: columna única. Cardinalidades: uno, cero o uno y cero o muchos. Línea continua: relación identificadora; discontinua: no identificadora. Ambas son FK declaradas.',
    '',
    'Tipos normalizados mediante SQLite en memoria. No incluye la tabla interna `migrations` ni cambios manuales de una instalación. No se consultaron ni modificaron datos de la base instalada.',
    '',
    '```mermaid',
    rtrim(diagram($schema, array_keys($schema), true)),
    '```',
    '',
    'Regenerar: `php docs/erd/generar.php` y después `node docs/erd/renderizar.cjs` (requiere `@viz-js/viz`; se puede pasar la ruta del paquete como argumento).',
    '',
];
file_put_contents(dirname(__DIR__).'/DIAGRAMA_ENTIDAD_RELACION.md', implode("\n", $md));

$dictionary = ['# Diccionario de datos', '', 'Esquema derivado de las migraciones, sin registros de usuarios. [Volver al diagrama](../DIAGRAMA_ENTIDAD_RELACION.md). Tipos normalizados por SQLite; no son una exportación de DDL MySQL.', ''];
foreach ($schema as $name => $table) {
    $dictionary[] = '## '.$name;
    $dictionary[] = '';
    $dictionary[] = 'Origen: '.implode(', ', array_map(fn ($migration) => '[`'.$migration.'`](../../database/migrations/'.$migration.')', $table['migrations'])).'.';
    $dictionary[] = '';
    $dictionary[] = '| Columna | Tipo normalizado | Claves | Acepta NULL |';
    $dictionary[] = '| --- | --- | --- | --- |';
    foreach ($table['columns'] as $column) {
        $keys = columnKeys($table, $column['name']);
        $nullable = $column['nullable'] && ! in_array('PK', $keys, true);
        $dictionary[] = '| `'.$column['name'].'` | `'.$column['type'].'` | '.implode(', ', $keys).' | '.($nullable ? 'Sí' : 'No').' |';
    }
    $dictionary[] = '';
    $dictionary[] = 'Clave primaria: '.implode(', ', array_map(fn ($column) => '`'.$column.'`', primaryColumns($table))).'.';
    $dictionary[] = '';
    foreach ($table['indexes'] as $index) {
        if (! $index['primary']) {
            $dictionary[] = '- '.($index['unique'] ? 'UNIQUE' : 'INDEX').' (`'.implode('`, `', $index['columns']).'`).';
        }
    }
    $dictionary[] = '';
    if ($table['foreign_keys']) {
        $dictionary[] = '| FK local | Destino | Al eliminar | Al actualizar |';
        $dictionary[] = '| --- | --- | --- | --- |';
        foreach ($table['foreign_keys'] as $fk) {
            $dictionary[] = '| `'.implode(', ', $fk['columns']).'` | `'.$fk['foreign_table'].'('.implode(', ', $fk['foreign_columns']).')` | '.strtoupper($fk['on_delete']).' | '.strtoupper($fk['on_update']).' |';
        }
    } else {
        $dictionary[] = 'Sin claves foráneas declaradas.';
    }
    $dictionary[] = '';
}
file_put_contents(__DIR__.'/DICCIONARIO_DATOS.md', implode("\n", $dictionary));
echo count($migrations).' migraciones; '.count($schema).' tablas; '.$columnCount.' columnas; '.$foreignKeyCount." FK. Cobertura completa verificada.\n";
