<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('source', 30)->default('requester')->index();
            $table->boolean('is_draft')->default(true)->index();
            $table->string('title', 200)->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('ticket_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requester_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('requester_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('location', 255)->nullable();
            $table->string('attention_type', 30)->nullable();
            $table->foreignId('impact_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('urgency_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_priority_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_status_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approval_status', 30)->default('not_required')->index();
            $table->foreignId('current_it_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('current_it_group_id')->nullable()->constrained('it_groups')->nullOnDelete();
            $table->foreignId('current_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_status', 30)->nullable()->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('escalation_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->longText('solution')->nullable();
            $table->foreignId('solution_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('solved_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedSmallInteger('reopen_count')->default(0);
            $table->dateTime('reopened_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['requester_id', 'created_at'], 'ticket_requester_idx');
            $table->index(['current_assignee_id', 'ticket_status_id'], 'ticket_assignee_status_idx');
            $table->index(['current_it_group_id', 'ticket_status_id'], 'ticket_group_status_idx');
            $table->index(['ticket_category_id', 'ticket_subcategory_id'], 'ticket_classification_idx');
        });

        Schema::create('ticket_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained()->restrictOnDelete();
            $table->longText('value')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'form_field_id']);
        });

        Schema::create('ticket_equipment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('inventory_code', 50);
            $table->string('equipment_name', 180)->nullable();
            $table->foreignId('linked_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'inventory_code']);
        });

        Schema::create('ticket_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approval_level_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('level_number');
            $table->string('level_name', 140);
            $table->string('approver_type', 40);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status', 30)->default('waiting')->index();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_for_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('comment', 2000)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'level_number']);
        });

        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('from_group_id')->nullable()->constrained('it_groups')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('to_group_id')->nullable()->constrained('it_groups')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->string('reason', 1500)->nullable();
            $table->string('rejection_reason', 1500)->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at'], 'assignment_ticket_date_idx');
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->longText('body');
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at'], 'comment_ticket_date_idx');
        });

        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('activity_date');
            $table->string('activity_type', 40);
            $table->unsignedInteger('minutes_spent')->default(0);
            $table->text('description');
            $table->text('diagnosis')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'activity_date'], 'activity_ticket_date_idx');
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ticket_comment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_activity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('context', 30)->index();
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->boolean('is_internal')->default(false)->index();
            $table->timestamps();
            $table->index(['ticket_id', 'context'], 'attachment_ticket_context_idx');
        });

        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 60)->index();
            $table->string('description', 1000);
            $table->json('from_values')->nullable();
            $table->json('to_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at'], 'history_ticket_date_idx');
        });

        Schema::create('ticket_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'related_ticket_id', 'type'], 'ticket_relation_unique');
        });

        Schema::create('ticket_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('comment', 2000)->nullable();
            $table->dateTime('confirmed_at');
            $table->string('confirmation_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_reviews');
        Schema::dropIfExists('ticket_relations');
        Schema::dropIfExists('ticket_histories');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_assignments');
        Schema::dropIfExists('ticket_approvals');
        Schema::dropIfExists('ticket_equipment_links');
        Schema::dropIfExists('ticket_field_values');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_sequences');
    }
};
