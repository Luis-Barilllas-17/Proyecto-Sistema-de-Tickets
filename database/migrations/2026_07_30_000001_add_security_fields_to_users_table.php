<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('password');
            $table->boolean('is_super_admin')->default(false)->after('status');
            $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('is_super_admin');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_failed_login_at')->nullable()->after('locked_until');
            $table->timestamp('last_login_at')->nullable()->after('last_failed_login_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_ip');
            $table->boolean('force_password_change')->default(false)->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'is_super_admin',
                'failed_login_attempts',
                'locked_until',
                'last_failed_login_at',
                'last_login_at',
                'last_login_ip',
                'password_changed_at',
                'force_password_change',
            ]);
        });
    }
};
