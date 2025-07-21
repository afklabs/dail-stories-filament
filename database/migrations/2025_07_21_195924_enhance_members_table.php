<?php

// ===== Migration: enhance_members_table.php =====
// Run: php artisan make:migration enhance_members_table --table=members

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Add security and tracking fields if they don't exist
            if (!Schema::hasColumn('members', 'login_count')) {
                $table->integer('login_count')->default(0)->after('last_login_at');
            }
            
            if (!Schema::hasColumn('members', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable()->after('login_count');
            }
            
            if (!Schema::hasColumn('members', 'registration_ip')) {
                $table->string('registration_ip')->nullable()->after('last_login_ip');
            }
            
            if (!Schema::hasColumn('members', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('registration_ip');
            }
            
            if (!Schema::hasColumn('members', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            
            if (!Schema::hasColumn('members', 'account_locked_at')) {
                $table->timestamp('account_locked_at')->nullable()->after('password_changed_at');
            }
            
            if (!Schema::hasColumn('members', 'failed_login_attempts')) {
                $table->integer('failed_login_attempts')->default(0)->after('account_locked_at');
            }

            // Add indexes for performance
            $table->index(['email', 'status'], 'idx_members_email_status');
            $table->index('last_login_at', 'idx_members_last_login');
            $table->index('created_at', 'idx_members_created');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'login_count',
                'last_login_ip', 
                'registration_ip',
                'user_agent',
                'password_changed_at',
                'account_locked_at',
                'failed_login_attempts'
            ]);
            
            $table->dropIndex('idx_members_email_status');
            $table->dropIndex('idx_members_last_login');
            $table->dropIndex('idx_members_created');
        });
    }
};
