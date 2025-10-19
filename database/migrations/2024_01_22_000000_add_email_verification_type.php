<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update email_type enum if it exists as enum
        // Otherwise, this is just documentation

        // Add to email_logs table metadata
        DB::statement("
ALTER TABLE email_logs
MODIFY COLUMN email_type VARCHAR(50)
COMMENT 'Types: welcome, password_reset, email_verification, promotional, notification'
");
    }

    public function down(): void
    {
        // No rollback needed
    }
};
