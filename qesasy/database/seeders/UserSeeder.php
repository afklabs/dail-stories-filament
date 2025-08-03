<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Member;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('👥 Creating Users and Members...');

        // Ensure roles exist before assigning them
        $this->ensureRolesExist();

        // Create Admin Users
        $this->createAdminUsers();

        // Create Content Team Users
        $this->createContentUsers();

        // Create Demo Members with Admin Access
        $this->createDemoMembers();

        $this->command->info('✅ Users and Members created successfully!');
    }

    private function ensureRolesExist(): void
    {
        $requiredRoles = ['super_admin', 'admin', 'editor', 'author', 'moderator', 'viewer', 'member_admin'];

        foreach ($requiredRoles as $roleName) {
            if (!Role::where('name', $roleName)->exists()) {
                $this->command->warn("⚠ Role '{$roleName}' does not exist. Please run RoleSeeder first.");
            }
        }
    }

    private function createAdminUsers(): void
    {
        $this->command->info('Creating admin users...');

        // Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super_admin');
        $this->command->info("  ✓ Super Admin: super@admin.com / password");

        // System Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@dailystories.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');
        $this->command->info("  ✓ Admin: admin@dailystories.com / admin123");

        // Backup Admin (different password for security)
        $backupAdmin = User::firstOrCreate(
            ['email' => 'backup@admin.com'],
            [
                'name' => 'Backup Administrator',
                'password' => Hash::make('backup_admin_2024'),
                'email_verified_at' => now(),
            ]
        );
        $backupAdmin->assignRole('admin');
        $this->command->info("  ✓ Backup Admin: backup@admin.com / backup_admin_2024");
    }

    private function createContentUsers(): void
    {
        $this->command->info('Creating content team users...');

        // Content Editor
        $editor = User::firstOrCreate(
            ['email' => 'editor@dailystories.com'],
            [
                'name' => 'Content Editor',
                'password' => Hash::make('editor123'),
                'email_verified_at' => now(),
            ]
        );
        $editor->assignRole('editor');
        $this->command->info("  ✓ Editor: editor@dailystories.com / editor123");

        // Senior Author
        $author1 = User::firstOrCreate(
            ['email' => 'john.author@dailystories.com'],
            [
                'name' => 'John Smith',
                'password' => Hash::make('author123'),
                'email_verified_at' => now(),
            ]
        );
        $author1->assignRole('author');

        // Junior Author
        $author2 = User::firstOrCreate(
            ['email' => 'jane.writer@dailystories.com'],
            [
                'name' => 'Jane Doe',
                'password' => Hash::make('writer123'),
                'email_verified_at' => now(),
            ]
        );
        $author2->assignRole('author');

        $this->command->info("  ✓ Authors: john.author@dailystories.com / author123");
        $this->command->info("  ✓ Authors: jane.writer@dailystories.com / writer123");

        // Content Moderator
        $moderator = User::firstOrCreate(
            ['email' => 'moderator@dailystories.com'],
            [
                'name' => 'Content Moderator',
                'password' => Hash::make('moderator123'),
                'email_verified_at' => now(),
            ]
        );
        $moderator->assignRole('moderator');
        $this->command->info("  ✓ Moderator: moderator@dailystories.com / moderator123");

        // Analytics Viewer
        $viewer = User::firstOrCreate(
            ['email' => 'analytics@dailystories.com'],
            [
                'name' => 'Analytics Viewer',
                'password' => Hash::make('viewer123'),
                'email_verified_at' => now(),
            ]
        );
        $viewer->assignRole('viewer');
        $this->command->info("  ✓ Viewer: analytics@dailystories.com / viewer123");
    }

    private function createDemoMembers(): void
    {
        $this->command->info('Creating demo members with admin access...');

        // Demo Member with Admin Access
        $memberAdmin = Member::firstOrCreate(
            ['email' => 'member@admin.com'],
            [
                'name' => 'Demo Member Admin',
                'password' => Hash::make('member123'),
                'phone' => '+1234567890',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'status' => 'active',
                'email_verified_at' => now(),
                'last_login_at' => now(),
                'registration_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'device_id' => 'seed_001',
            ]
        );
        // Note: Members don't use Spatie roles - they get admin access via canAccessPanel() method
        $this->command->info("  ✓ Member Admin: member@admin.com / member123");

        // Premium Member
        $premiumMember = Member::firstOrCreate(
            ['email' => 'premium@member.com'],
            [
                'name' => 'Premium Member',
                'password' => Hash::make('premium123'),
                'phone' => '+1234567891',
                'date_of_birth' => '1985-05-15',
                'gender' => 'female',
                'status' => 'active',
                'email_verified_at' => now(),
                'last_login_at' => now()->subDays(2),
                'registration_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'device_id' => 'seed_002',
            ]
        );
        $this->command->info("  ✓ Premium Member: premium@member.com / premium123");

        // Regular Active Member (no admin access)
        $regularMember = Member::firstOrCreate(
            ['email' => 'regular@member.com'],
            [
                'name' => 'Regular Member',
                'password' => Hash::make('regular123'),
                'phone' => '+1234567892',
                'date_of_birth' => '1995-12-20',
                'gender' => 'male',
                'status' => 'active',
                'email_verified_at' => now(),
                'last_login_at' => now()->subHours(5),
                'registration_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'device_id' => 'seed_003',
            ]
        );
        $this->command->info("  ✓ Regular Member: regular@member.com / regular123 (no admin access)");

        // Test Member (for development)
        if (app()->environment(['local', 'development', 'testing'])) {
            $testMember = Member::firstOrCreate(
                ['email' => 'test@member.com'],
                [
                    'name' => 'Test Member',
                    'password' => Hash::make('test123'),
                    'phone' => '+1234567893',
                    'date_of_birth' => '2000-01-01',
                    'gender' => 'female',
                    'status' => 'active',
                    'email_verified_at' => now(),
                    'last_login_at' => now()->subMinutes(30),
                    'registration_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'device_id' => 'seed_004',
                ]
            );
            $this->command->info("  ✓ Test Member: test@member.com / test123 (development only)");
        }
    }
}
