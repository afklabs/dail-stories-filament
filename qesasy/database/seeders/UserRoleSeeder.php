<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🔐 Setting up user roles and permissions...');

        // Create roles if they don't exist
        $roles = [
            'super_admin' => 'Super Administrator with full access',
            'admin' => 'Administrator with most access',
            'editor' => 'Content editor',
            'moderator' => 'Content moderator',
        ];

        foreach ($roles as $roleName => $description) {
            $role = Role::firstOrCreate(['name' => $roleName], [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
            $this->command->info("✓ Role '{$roleName}' ready");
        }

        // Assign roles to existing users
        $userRoleAssignments = [
            'admin@dailystories.com' => 'super_admin',
            // Add more user email => role assignments here
        ];

        foreach ($userRoleAssignments as $email => $roleName) {
            $user = User::where('email', $email)->first();
            
            if ($user) {
                // Remove existing roles and assign new one
                $user->syncRoles([$roleName]);
                $this->command->info("✓ User '{$email}' assigned role '{$roleName}'");
            } else {
                $this->command->warn("⚠ User '{$email}' not found");
            }
        }

        // Assign all permissions to super_admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $allPermissions = Permission::all();
            $superAdminRole->syncPermissions($allPermissions);
            $this->command->info("✓ Super admin role assigned all permissions");
        }

        $this->command->info('✅ User roles setup completed!');
    }
}