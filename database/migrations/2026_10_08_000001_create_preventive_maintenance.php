<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->text('description');
            $table->unsignedTinyInteger('frequency_months');
            $table->unsignedSmallInteger('lead_days')->default(7);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->time('default_start_time')->nullable();
            $table->foreignId('assigned_it_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('assigned_it_group_id')->nullable()->constrained('it_groups')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('maintenance_plan_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->date('start_date');
            $table->date('next_due_date')->index();
            $table->date('last_completed_on')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['maintenance_plan_id', 'equipment_id'], 'maintenance_plan_equipment_unique');
        });

        Schema::create('maintenance_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->text('instructions')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('maintenance_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_plan_equipment_id')->constrained('maintenance_plan_equipment')->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('ticket_id')->nullable()->unique()->constrained('tickets')->nullOnDelete();
            $table->date('scheduled_for')->index();
            $table->dateTime('scheduled_start_at')->nullable();
            $table->dateTime('scheduled_end_at')->nullable();
            $table->string('status', 40)->default('scheduled')->index();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('generation_source', 30)->default('automatic');
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('result', 40)->nullable();
            $table->longText('observations')->nullable();
            $table->string('equipment_previous_status', 40)->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['maintenance_plan_id', 'equipment_id', 'scheduled_for'],
                'maintenance_order_occurrence_unique',
            );
            $table->index(['status', 'scheduled_for'], 'maintenance_order_status_date_idx');
        });

        Schema::create('maintenance_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_checklist_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->string('status', 30)->default('pending');
            $table->text('observation')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('description', 1000)->nullable();
            $table->timestamps();
            $table->index(['maintenance_order_id', 'created_at'], 'maintenance_evidence_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_evidences');
        Schema::dropIfExists('maintenance_order_items');
        Schema::dropIfExists('maintenance_orders');
        Schema::dropIfExists('maintenance_sequences');
        Schema::dropIfExists('maintenance_checklist_items');
        Schema::dropIfExists('maintenance_plan_equipment');
        Schema::dropIfExists('maintenance_plans');
    }
};
