<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supply;
use App\Models\TicketEquipmentLink;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModuleSixSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            ['Consultar inventario', 'Consultar equipos, historial e insumos técnicos', 'inventory.view'],
            ['Gestionar equipos', 'Crear y actualizar fichas, especificaciones y licencias de equipos', 'inventory.equipment_manage'],
            ['Asignar y trasladar', 'Registrar asignaciones, devoluciones, traslados y bajas', 'inventory.assignment_manage'],
            ['Historial técnico', 'Registrar mantenimientos, reparaciones y cambios técnicos', 'inventory.technical_history'],
            ['Gestionar existencias', 'Administrar insumos y movimientos de existencias', 'inventory.stock_manage'],
            ['Reservar insumos', 'Reservar y liberar insumos durante la atención de tickets', 'inventory.consume'],
        ])->mapWithKeys(function (array $definition) {
            [$name, $description, $slug] = $definition;
            $permission = Permission::updateOrCreate(['slug' => $slug], [
                'module' => 'Inventario técnico', 'action' => $name, 'name' => $name, 'description' => $description,
            ]);

            return [$slug => $permission];
        });

        foreach ([
            'super-administrador' => $permissions->keys()->all(),
            'jefatura-it' => $permissions->keys()->all(),
            'encargado-area-it' => $permissions->keys()->all(),
            'tecnico-it' => ['inventory.view', 'inventory.technical_history', 'inventory.consume'],
            'programador-it' => ['inventory.view', 'inventory.technical_history', 'inventory.consume'],
        ] as $roleSlug => $permissionSlugs) {
            Role::where('slug', $roleSlug)->first()?->permissions()
                ->syncWithoutDetaching($permissions->only($permissionSlugs)->pluck('id'));
        }

        $this->seedEquipment();
        $this->seedSupplies();
    }

    private function seedEquipment(): void
    {
        $admin = User::where('email', 'admin@zolicguate.com')->first();
        if (! $admin) {
            return;
        }
        $definitions = [
            ['ZOLIC-PC-001', 'computer', 'Estación de operaciones 01', 'Dell', 'OptiPlex 7090', 'DL7090-0001', '10.20.10.41', '00:1A:2B:3C:4D:01', 'OPE-PC-001', 'Operaciones', 'OPE', 'OPE-POR', 'usuario.ticket@zolicguate.com', 'assigned'],
            ['ZOLIC-SRV-001', 'server', 'Servidor de aplicaciones institucionales', 'HPE', 'ProLiant DL380', 'HPE380-0001', '10.10.1.10', '00:1A:2B:3C:4D:02', 'SRV-APP-01', 'Centro de datos', 'IT-INF', 'INF-RED', 'infraestructura@zolicguate.com', 'assigned'],
            ['ZOLIC-PRN-003', 'printer', 'Impresora de documentación portuaria', 'HP', 'LaserJet Enterprise', 'HPENT-0003', '10.20.10.75', '00:1A:2B:3C:4D:03', 'OPE-PRN-003', 'Operaciones Portuarias', 'OPE', 'OPE-POR', null, 'available'],
            ['ZOLIC-SW-010', 'network_device', 'Switch de distribución administrativo', 'Cisco', 'Catalyst 9200', 'CSC9200-0010', '10.10.2.10', '00:1A:2B:3C:4D:04', 'SW-ADM-010', 'Cuarto de comunicaciones', 'IT-INF', 'INF-RED', 'infraestructura@zolicguate.com', 'maintenance'],
            ['ZOLIC-LAP-021', 'computer', 'Portátil de desarrollo 21', 'Lenovo', 'ThinkPad T14', 'LNV-T14-0021', '10.30.4.21', '00:1A:2B:3C:4D:05', 'DEV-LAP-021', 'Desarrollo y Aplicaciones', 'IT-DEV', 'DEV-APP', 'aplicaciones@zolicguate.com', 'assigned'],
        ];

        foreach ($definitions as [$code, $type, $name, $brand, $model, $serial, $ip, $mac, $hostname, $location, $areaCode, $departmentCode, $email, $status]) {
            $equipment = Equipment::updateOrCreate(['inventory_code' => $code], [
                'asset_type' => $type,
                'name' => $name,
                'brand' => $brand,
                'model' => $model,
                'serial_number' => $serial,
                'ip_address' => $ip,
                'mac_address' => $mac,
                'hostname' => $hostname,
                'network_segment' => str($ip)->beforeLast('.').'.0/24',
                'processor' => $type === 'server' ? 'Intel Xeon Silver' : ($type === 'computer' ? 'Intel Core i7' : null),
                'memory_mb' => $type === 'server' ? 65536 : ($type === 'computer' ? 16384 : null),
                'storage_gb' => $type === 'server' ? 4096 : ($type === 'computer' ? 512 : null),
                'operating_system' => $type === 'server' ? 'Ubuntu Server LTS' : ($type === 'computer' ? 'Windows 11 Pro' : null),
                'purchase_date' => now()->subYears(2)->toDateString(),
                'warranty_expires_at' => now()->addYear()->toDateString(),
                'area_id' => Area::where('code', $areaCode)->value('id'),
                'department_id' => Department::where('code', $departmentCode)->value('id'),
                'responsible_user_id' => $email ? User::where('email', $email)->value('id') : null,
                'location' => $location,
                'status' => $status,
                'condition_notes' => $status === 'maintenance' ? 'Mantenimiento preventivo en curso.' : 'Equipo en condición operativa.',
                'is_active' => true,
                'created_by_id' => $admin->id,
                'updated_by_id' => $admin->id,
            ]);
            $equipment->events()->firstOrCreate(['title' => 'Alta en inventario'], [
                'event_type' => 'note', 'description' => 'Activo incorporado al inventario institucional.',
                'performed_at' => now()->subYears(2), 'created_by_id' => $admin->id,
            ]);
        }

        $server = Equipment::where('inventory_code', 'ZOLIC-SRV-001')->first();
        $server?->licenses()->updateOrCreate(['name' => 'Sistema operativo empresarial'], [
            'license_reference' => 'LIC-SRV-001', 'seats' => 1, 'expires_at' => now()->addMonths(10),
            'notes' => 'Referencia institucional; no contiene la clave secreta.', 'created_by_id' => $admin->id,
        ]);

        TicketEquipmentLink::query()->each(function (TicketEquipmentLink $link): void {
            $asset = Equipment::where('inventory_code', $link->inventory_code)->first();
            if ($asset) {
                $link->update(['equipment_id' => $asset->id, 'equipment_name' => $asset->name]);
            }
        });
    }

    private function seedSupplies(): void
    {
        $admin = User::where('email', 'admin@zolicguate.com')->first();
        if (! $admin) {
            return;
        }
        foreach ([
            ['INS-CAB-C6', 'Cable UTP categoría 6', 'Cableado', 'meter', 150, 50, 'Bodega IT · Estante A1'],
            ['INS-RJ45', 'Conector RJ45', 'Conectores', 'unit', 80, 25, 'Bodega IT · Gaveta A2'],
            ['INS-SSD-1TB', 'Disco SSD 1 TB', 'Almacenamiento', 'unit', 8, 3, 'Bodega IT · Gabinete B1'],
            ['INS-RAM-16', 'Memoria RAM DDR4 16 GB', 'Memorias', 'unit', 4, 4, 'Bodega IT · Gabinete B2'],
            ['INS-TINTA-BK', 'Tinta negra para impresora', 'Impresión', 'unit', 2, 3, 'Bodega IT · Estante C1'],
        ] as [$code, $name, $category, $unit, $quantity, $minimum, $location]) {
            $supply = Supply::updateOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'unit' => $unit,
                'minimum_quantity' => $minimum, 'storage_location' => $location,
                'description' => 'Insumo técnico de uso institucional.', 'is_active' => true,
                'created_by_id' => $admin->id, 'updated_by_id' => $admin->id,
            ]);
            if (! $supply->movements()->exists()) {
                $supply->update(['stock_quantity' => $quantity]);
                $supply->movements()->create([
                    'movement_type' => 'entry', 'quantity_delta' => $quantity, 'balance_before' => 0,
                    'balance_after' => $quantity, 'justification' => 'Carga inicial del inventario técnico.',
                    'responsible_id' => $admin->id, 'authorized_by_id' => $admin->id,
                ]);
            }
        }
    }
}
