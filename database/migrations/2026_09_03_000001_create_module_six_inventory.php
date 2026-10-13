<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_code', 50)->unique();
            $table->string('asset_type', 40)->index();
            $table->string('name', 180);
            $table->string('brand', 100)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 150)->nullable()->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable()->unique();
            $table->string('hostname', 255)->nullable()->index();
            $table->string('network_segment', 180)->nullable();
            $table->text('connectivity_notes')->nullable();
            $table->string('processor', 180)->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->decimal('storage_gb', 10, 2)->nullable();
            $table->string('operating_system', 180)->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->decimal('acquisition_cost', 14, 2)->nullable();
            $table->json('additional_specifications')->nullable();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('location', 255);
            $table->string('status', 40)->default('available')->index();
            $table->text('condition_notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['asset_type', 'status'], 'equipment_type_status_idx');
            $table->index(['area_id', 'department_id'], 'equipment_org_idx');
        });

        Schema::create('equipment_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('license_reference', 255)->nullable();
            $table->unsignedInteger('seats')->default(1);
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['equipment_id', 'name'], 'equipment_license_name_unique');
        });

        Schema::create('equipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->string('title', 180);
            $table->longText('description');
            $table->dateTime('performed_at');
            $table->string('provider', 180)->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['equipment_id', 'performed_at'], 'equipment_event_date_idx');
        });

        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->string('movement_type', 40)->index();
            $table->foreignId('from_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('to_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_location', 255)->nullable();
            $table->string('to_location', 255)->nullable();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->dateTime('effective_at');
            $table->string('justification', 2000);
            $table->foreignId('authorized_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['equipment_id', 'effective_at'], 'equipment_movement_date_idx');
        });

        Schema::table('ticket_equipment_links', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('ticket_id');
            $table->foreign('equipment_id', 'ticket_equipment_inventory_fk')
                ->references('id')->on('equipment')->nullOnDelete();
            $table->index('equipment_id', 'ticket_equipment_inventory_idx');
        });

        Schema::create('supplies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->string('category', 100)->index();
            $table->string('unit', 40);
            $table->decimal('stock_quantity', 12, 2)->default(0);
            $table->decimal('minimum_quantity', 12, 2)->default(0);
            $table->string('storage_location', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'category'], 'supply_active_category_idx');
        });

        Schema::create('ticket_supply_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained('supplies')->restrictOnDelete();
            $table->decimal('quantity_reserved', 12, 2);
            $table->decimal('quantity_consumed', 12, 2)->nullable();
            $table->string('status', 30)->default('reserved')->index();
            $table->string('notes', 2000)->nullable();
            $table->foreignId('recorded_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('consumed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('consumed_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'supply_id'], 'ticket_supply_unique');
        });

        Schema::create('supply_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('supplies')->restrictOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('ticket_supply_usage_id')->nullable();
            $table->foreign('ticket_supply_usage_id', 'supply_movement_usage_fk')
                ->references('id')->on('ticket_supply_usages')->nullOnDelete();
            $table->string('movement_type', 30)->index();
            $table->decimal('quantity_delta', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('justification', 2000);
            $table->foreignId('responsible_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supply_id', 'created_at'], 'supply_movement_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_movements');
        Schema::dropIfExists('ticket_supply_usages');
        Schema::dropIfExists('supplies');
        Schema::table('ticket_equipment_links', function (Blueprint $table) {
            $table->dropForeign('ticket_equipment_inventory_fk');
            $table->dropIndex('ticket_equipment_inventory_idx');
            $table->dropColumn('equipment_id');
        });
        Schema::dropIfExists('equipment_movements');
        Schema::dropIfExists('equipment_events');
        Schema::dropIfExists('equipment_licenses');
        Schema::dropIfExists('equipment');
    }
};
