<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 191)->unique();
            $table->foreignId('ticket_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->string('severity', 20)->index();
            $table->string('title', 180);
            $table->string('message', 1000);
            $table->string('status', 20)->default('open')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity', 'detected_at'], 'system_alert_status_idx');
        });

        Schema::create('ticket_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('level', 30)->index();
            $table->string('source', 20)->default('manual');
            $table->string('trigger_key', 191);
            $table->string('reason', 2000);
            $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('escalated_at');
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'level', 'trigger_key'], 'ticket_escalation_trigger_unique');
            $table->index(['level', 'escalated_at'], 'ticket_escalation_level_date_idx');
        });

        Schema::create('ticket_third_party_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 180);
            $table->string('reference', 120)->nullable();
            $table->text('description');
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('requested_at');
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ticket_id', 'status'], 'third_party_ticket_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_third_party_dependencies');
        Schema::dropIfExists('ticket_escalations');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('notifications');
    }
};
