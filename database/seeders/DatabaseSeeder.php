<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * 
     * This seeder follows the correct order for Filament + Spatie Permission setup:
     * 1. Permissions first (required for roles)
     * 2. Roles with permission assignments
     * 3. Users with role assignments
     * 4. Content (categories, tags, etc.)
     * 5. Demo data
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('🚀 Starting Database Seeding with Filament + Spatie Permission setup...');

        // ✅ STEP 1: Create Permissions FIRST
        // This must run before roles since roles need permissions to assign
        $this->call([
            PermissionSeeder::class,  // Your updated permission seeder
        ]);

        // ✅ STEP 2: Create Roles and assign permissions
        // Roles depend on permissions existing
        $this->call([
            RoleSeeder::class,
        ]);

        // ✅ STEP 3: Create Users and Members with role assignments
        // Users depend on roles existing
        $this->call([
            UserSeeder::class,
        ]);

        // ✅ STEP 4: Create Content Structure
        // These can run in any order after users are created
        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
            MemberSeeder::class,  // Additional members (your existing seeder)
            StorySeeder::class,
            StoryInteractionSeeder::class,
            StoryRatingSeeder::class,
            StoryAnalyticsSeeder::class,
        ]);

        // ✅ STEP 5: Demo Data (Development Only)
        if (app()->environment(['local', 'development', 'testing'])) {
            $this->call([
                DemoDataSeeder::class,
            ]);
        }

        $this->command->info('');
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');

        // Display login credentials for convenience
        $this->displayLoginCredentials();

        // Display important notes
        $this->displayImportantNotes();
    }

    /**
     * Display login credentials for easy access
     */
    private function displayLoginCredentials(): void
    {
        $this->command->info('🔐 LOGIN CREDENTIALS:');
        $this->command->info('┌─────────────────────────────────────────────────────────┐');
        $this->command->info('│                     ADMIN USERS                        │');
        $this->command->info('├─────────────────────────────────────────────────────────┤');
        $this->command->info('│ Super Admin: super@admin.com / password                │');
        $this->command->info('│ Admin:       admin@dailystories.com / admin123         │');
        $this->command->info('│ Backup:      backup@admin.com / backup_admin_2024      │');
        $this->command->info('├─────────────────────────────────────────────────────────┤');
        $this->command->info('│                   CONTENT TEAM                         │');
        $this->command->info('├─────────────────────────────────────────────────────────┤');
        $this->command->info('│ Editor:      editor@dailystories.com / editor123       │');
        $this->command->info('│ Author 1:    john.author@dailystories.com / author123  │');
        $this->command->info('│ Author 2:    jane.writer@dailystories.com / writer123  │');
        $this->command->info('│ Moderator:   moderator@dailystories.com / moderator123 │');
        $this->command->info('│ Viewer:      analytics@dailystories.com / viewer123    │');
        $this->command->info('├─────────────────────────────────────────────────────────┤');
        $this->command->info('│                     MEMBERS                             │');
        $this->command->info('├─────────────────────────────────────────────────────────┤');
        $this->command->info('│ Member Admin: member@admin.com / member123             │');
        $this->command->info('│ Premium:      premium@member.com / premium123          │');
        $this->command->info('│ Regular:      regular@member.com / regular123          │');

        if (app()->environment(['local', 'development', 'testing'])) {
            $this->command->info('│ Test Member:  test@member.com / test123                │');
        }

        $this->command->info('└─────────────────────────────────────────────────────────┘');
    }

    /**
     * Display important setup notes
     */
    private function displayImportantNotes(): void
    {
        $this->command->info('');
        $this->command->info('📋 IMPORTANT NOTES:');
        $this->command->info('');

        $this->command->info('🎭 ROLES CREATED:');
        $this->command->info('  • super_admin: Full system access');
        $this->command->info('  • admin: Management access');
        $this->command->info('  • editor: Content management');
        $this->command->info('  • author: Story creation');
        $this->command->info('  • moderator: Content moderation');
        $this->command->info('  • viewer: Read-only access');
        $this->command->info('  • member_admin: Member with admin panel access');
        $this->command->info('');

        $this->command->info('🔑 PERMISSION SYSTEM:');
        $this->command->info('  • Filament Shield permissions are automatically created');
        $this->command->info('  • Custom permissions match your existing structure');
        $this->command->info('  • Super admin has ALL permissions automatically');
        $this->command->info('  • Member model can access admin panel with member_admin role');
        $this->command->info('');

        $this->command->info('🚀 NEXT STEPS:');
        $this->command->info('  1. Login to admin panel: /admin');
        $this->command->info('  2. Check Roles & Permissions in User Management');
        $this->command->info('  3. Test different user roles and access levels');
        $this->command->info('  4. Customize permissions as needed for your workflow');
        $this->command->info('');

        $this->command->info('⚠️  SECURITY REMINDERS:');
        $this->command->info('  • Change default passwords in production!');
        $this->command->info('  • Remove/disable demo accounts in production');
        $this->command->info('  • Review role permissions before going live');
        $this->command->info('  • Enable 2FA for admin accounts in production');
        $this->command->info('');

        if (app()->environment(['local', 'development'])) {
            $this->command->info('🧪 DEVELOPMENT MODE:');
            $this->command->info('  • Demo data has been created');
            $this->command->info('  • Test accounts are available');
            $this->command->info('  • All features are enabled for testing');
        }
    }
}
