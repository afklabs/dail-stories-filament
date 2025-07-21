<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('text'); // text, number, boolean, json, array
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['group', 'key']);
        });

        // Insert default settings
        $this->insertDefaultSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }

    private function insertDefaultSettings(): void
    {
        $settings = [
            // Story Settings
            [
                'key' => 'story_active_from_default',
                'value' => '"now"',
                'group' => 'story',
                'type' => 'select',
                'description' => 'Default value for "Active From" when creating new stories',
            ],
            [
                'key' => 'story_active_until_hours',
                'value' => '24', // Store as string but with type field
                'group' => 'story',
                'type' => 'integer', // This tells the model to cast to integer
                'description' => 'Number of hours to add to "Active From" date for "Active Until"',
            ],
                        
            // Cache Settings
            [
                'key' => 'cache.default_duration',
                'value' => '900',
                'group' => 'cache',
                'type' => 'number',
                'description' => 'Default cache duration in seconds',
            ],
            [
                'key' => 'cache.analytics_duration',
                'value' => '1800',
                'group' => 'cache',
                'type' => 'number',
                'description' => 'Analytics cache duration in seconds',
            ],
            [
                'key' => 'cache.dashboard_duration',
                'value' => '300',
                'group' => 'cache',
                'type' => 'number',
                'description' => 'Dashboard cache duration in seconds',
            ],
            [
                'key' => 'cache.enabled',
                'value' => 'true',
                'group' => 'cache',
                'type' => 'boolean',
                'description' => 'Enable or disable caching',
            ],
            
            // Performance Settings
            [
                'key' => 'performance.query_cache',
                'value' => 'true',
                'group' => 'performance',
                'type' => 'boolean',
                'description' => 'Enable query caching',
            ],
            [
                'key' => 'performance.image_optimization',
                'value' => 'true',
                'group' => 'performance',
                'type' => 'boolean',
                'description' => 'Enable image optimization',
            ],
            
            // Security Settings
            [
                'key' => 'security.rate_limiting',
                'value' => 'true',
                'group' => 'security',
                'type' => 'boolean',
                'description' => 'Enable rate limiting',
            ],
            [
                'key' => 'security.audit_logging',
                'value' => 'true',
                'group' => 'security',
                'type' => 'boolean',
                'description' => 'Enable audit logging',
            ],
            [
                'key' => 'security.2fa_required',
                'value' => 'false',
                'group' => 'security',
                'type' => 'boolean',
                'description' => 'Require two-factor authentication',
            ],
            
            // Notification Settings
            [
                'key' => 'notifications.new_member',
                'value' => 'true',
                'group' => 'notifications',
                'type' => 'boolean',
                'description' => 'Send notification on new member registration',
            ],
            [
                'key' => 'notifications.story_approval',
                'value' => 'true',
                'group' => 'notifications',
                'type' => 'boolean',
                'description' => 'Send notification when story needs approval',
            ],
        ];

        foreach ($settings as $setting) {
            \DB::table('settings')->insert($setting);
        }
    }
};
