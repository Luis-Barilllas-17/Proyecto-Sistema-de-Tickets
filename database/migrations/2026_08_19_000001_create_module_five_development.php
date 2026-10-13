<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('request_type', 40)->index();
            $table->string('project_name', 200)->nullable();
            $table->longText('business_need')->nullable();
            $table->longText('objective')->nullable();
            $table->longText('scope')->nullable();
            $table->text('expected_benefits')->nullable();
            $table->string('affected_system', 200)->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->longText('technical_restrictions')->nullable();
            $table->string('information_classification', 30)->default('internal')->index();
            $table->boolean('contains_sensitive_data')->default(false);
            $table->text('sensitive_data_description')->nullable();
            $table->boolean('involves_third_parties')->default(false);
            $table->text('third_party_details')->nullable();
            $table->text('access_overview')->nullable();
            $table->string('repository_reference', 255)->nullable();
            $table->string('lifecycle_status', 40)->default('analysis')->index();
            $table->timestamps();
        });

        Schema::create('development_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_request_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('type', 40)->index();
            $table->string('title', 200);
            $table->longText('description');
            $table->longText('acceptance_criteria')->nullable();
            $table->longText('technical_restrictions')->nullable();
            $table->string('priority', 20)->default('medium')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['development_request_id', 'code'], 'development_requirement_code_unique');
        });

        Schema::create('development_security_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_request_id')->constrained()->cascadeOnDelete();
            $table->string('control_type', 40);
            $table->boolean('is_required')->default(false);
            $table->text('requirement_details')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['development_request_id', 'control_type'], 'development_security_control_unique');
        });

        Schema::create('development_access_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_request_id')->constrained()->cascadeOnDelete();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->text('permissions');
            $table->text('data_scope')->nullable();
            $table->boolean('includes_sensitive_data')->default(false);
            $table->boolean('third_party_access')->default(false);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['development_request_id', 'name'], 'development_access_profile_unique');
        });

        Schema::create('development_test_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_request_id')->constrained()->cascadeOnDelete();
            $table->string('test_type', 40)->index();
            $table->string('title', 180);
            $table->string('environment', 80);
            $table->string('result', 30)->index();
            $table->dateTime('executed_at');
            $table->foreignId('executed_by_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['development_request_id', 'test_type', 'result'], 'development_test_result_idx');
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreignId('development_test_evidence_id')->nullable()->after('ticket_activity_id')
                ->constrained()->cascadeOnDelete();
        });

        Schema::create('development_deployment_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_request_id');
            $table->foreign('development_request_id', 'dev_deployment_request_fk')
                ->references('id')->on('development_requests')->cascadeOnDelete();
            $table->string('version', 60);
            $table->string('environment', 40)->default('production');
            $table->longText('release_notes');
            $table->longText('deployment_plan');
            $table->longText('rollback_plan');
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_comment', 2000)->nullable();
            $table->timestamps();
            $table->index(['development_request_id', 'created_at'], 'development_deployment_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('development_deployment_authorizations');
        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('development_test_evidence_id');
        });
        Schema::dropIfExists('development_test_evidences');
        Schema::dropIfExists('development_access_profiles');
        Schema::dropIfExists('development_security_controls');
        Schema::dropIfExists('development_requirements');
        Schema::dropIfExists('development_requests');
    }
};
