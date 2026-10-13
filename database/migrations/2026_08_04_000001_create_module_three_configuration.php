<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 160);
            $table->string('type', 30)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['ticket_category_id', 'code']);
        });

        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->string('color', 7)->default('#225C9A');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('impact_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedTinyInteger('value')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('urgency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedTinyInteger('value')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80)->unique();
            $table->string('color', 7)->default('#225C9A');
            $table->unsignedTinyInteger('weight')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ticket_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_form_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('label', 160);
            $table->string('type', 30)->index();
            $table->string('placeholder', 255)->nullable();
            $table->text('help_text')->nullable();
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('min_value')->nullable();
            $table->unsignedInteger('max_value')->nullable();
            $table->timestamps();
            $table->unique(['ticket_form_id', 'name']);
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('minimum_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('approval_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level_number');
            $table->string('name', 140);
            $table->string('approver_type', 40);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('allow_delegation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['approval_workflow_id', 'level_number']);
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 500);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['delegator_user_id', 'starts_at', 'ends_at'], 'delegation_period_idx');
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('it_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('it_group_id')->nullable()->constrained('it_groups')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->unsignedSmallInteger('precedence')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['ticket_category_id', 'ticket_subcategory_id', 'precedence'], 'routing_scope_idx');
        });

        Schema::create('priority_matrix_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impact_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('urgency_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_priority_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['impact_level_id', 'urgency_level_id']);
        });

        Schema::create('service_level_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_priority_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->unsignedInteger('escalation_minutes');
            $table->boolean('business_hours_only')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['ticket_priority_id', 'ticket_category_id', 'ticket_subcategory_id'], 'sla_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_level_targets');
        Schema::dropIfExists('priority_matrix_rules');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_levels');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('ticket_forms');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('urgency_levels');
        Schema::dropIfExists('impact_levels');
        Schema::dropIfExists('ticket_statuses');
        Schema::dropIfExists('ticket_subcategories');
        Schema::dropIfExists('ticket_categories');
    }
};
