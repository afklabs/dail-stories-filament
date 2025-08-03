<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('🎭 Creating Roles and assigning permissions...');

        // Define roles with their permission groups
        $roles = [
            'super_admin' => [
                'permissions' => 'all', // Will get ALL permissions
                'guard_name' => 'web',
            ],
            'admin' => [
                'permissions' => [
                    // Story management
                    'view_any_story',
                    'view_story',
                    'create_story',
                    'update_story',
                    'delete_story',
                    'list stories',
                    'show stories',
                    'create stories',
                    'update stories',
                    'delete stories',
                    'publish stories',
                    'unpublish stories',

                    // Category & Tag management
                    'view_any_category',
                    'view_category',
                    'create_category',
                    'update_category',
                    'delete_category',
                    'list categories',
                    'show categories',
                    'create categories',
                    'update categories',
                    'delete categories',
                    'view_any_tag',
                    'view_tag',
                    'create_tag',
                    'update_tag',
                    'delete_tag',
                    'list tags',
                    'show tags',
                    'create tags',
                    'update tags',
                    'delete tags',

                    // User management
                    'view_any_user',
                    'view_user',
                    'create_user',
                    'update_user',
                    'list users',
                    'show users',
                    'create users',
                    'update users',

                    // Member management
                    'view_any_member',
                    'view_member',
                    'create_member',
                    'update_member',
                    'delete_member',
                    'list members',
                    'show members',
                    'create members',
                    'update members',
                    'delete members',
                    'activate members',
                    'suspend members',

                    // Role management (limited)
                    'view_any_role',
                    'view_role',
                    'create_role',
                    'update_role',
                    'list roles',
                    'show roles',
                    'create roles',
                    'update roles',

                    // Analytics access
                    'view analytics',
                    'view member analytics',
                    'view story analytics',
                    'export analytics',

                    // System access
                    'view logs',
                    'manage settings',
                ],
                'guard_name' => 'web',
            ],
            'editor' => [
                'permissions' => [
                    // Story management
                    'view_any_story',
                    'view_story',
                    'create_story',
                    'update_story',
                    'list stories',
                    'show stories',
                    'create stories',
                    'update stories',
                    'publish stories',
                    'unpublish stories',

                    // Category & Tag management
                    'view_any_category',
                    'view_category',
                    'create_category',
                    'update_category',
                    'list categories',
                    'show categories',
                    'create categories',
                    'update categories',
                    'view_any_tag',
                    'view_tag',
                    'create_tag',
                    'update_tag',
                    'list tags',
                    'show tags',
                    'create tags',
                    'update tags',

                    // Limited member access
                    'view_any_member',
                    'view_member',
                    'list members',
                    'show members',

                    // Analytics access
                    'view story analytics',
                ],
                'guard_name' => 'web',
            ],
            'author' => [
                'permissions' => [
                    // Story management (limited)
                    'view_any_story',
                    'view_story',
                    'create_story',
                    'update_story',
                    'list stories',
                    'show stories',
                    'create stories',
                    'update stories',

                    // Read-only access to categories and tags
                    'view_any_category',
                    'view_category',
                    'list categories',
                    'show categories',
                    'view_any_tag',
                    'view_tag',
                    'list tags',
                    'show tags',

                    // Limited member access
                    'view_any_member',
                    'view_member',
                    'list members',
                    'show members',
                ],
                'guard_name' => 'web',
            ],
            'moderator' => [
                'permissions' => [
                    // Story moderation
                    'view_any_story',
                    'view_story',
                    'update_story',
                    'list stories',
                    'show stories',
                    'update stories',
                    'publish stories',
                    'unpublish stories',

                    // Member management
                    'view_any_member',
                    'view_member',
                    'update_member',
                    'list members',
                    'show members',
                    'update members',
                    'activate members',
                    'suspend members',

                    // Read access to categories and tags
                    'view_any_category',
                    'view_category',
                    'list categories',
                    'show categories',
                    'view_any_tag',
                    'view_tag',
                    'list tags',
                    'show tags',

                    // Member analytics
                    'view member analytics',
                ],
                'guard_name' => 'web',
            ],
            'viewer' => [
                'permissions' => [
                    // Read-only story access
                    'view_any_story',
                    'view_story',
                    'list stories',
                    'show stories',

                    // Read-only member access
                    'view_any_member',
                    'view_member',
                    'list members',
                    'show members',

                    // Read-only categories and tags
                    'view_any_category',
                    'view_category',
                    'list categories',
                    'show categories',
                    'view_any_tag',
                    'view_tag',
                    'list tags',
                    'show tags',
                ],
                'guard_name' => 'web',
            ],
            'member_admin' => [
                'permissions' => [
                    // Limited story access
                    'view_any_story',
                    'view_story',
                    'list stories',
                    'show stories',

                    // Own member management
                    'view_any_member',
                    'view_member',
                    'list members',
                    'show members',
                ],
                'guard_name' => 'web',
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $this->command->info("Creating role: {$roleName}");

            // Create or update role
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => $roleData['guard_name']
            ]);

            // Assign permissions
            if ($roleData['permissions'] === 'all') {
                // Super admin gets all permissions
                $allPermissions = Permission::all();
                $role->syncPermissions($allPermissions);
                $this->command->info("  ✓ Assigned ALL permissions ({$allPermissions->count()}) to {$roleName}");
            } else {
                // Assign specific permissions
                $validPermissions = [];
                foreach ($roleData['permissions'] as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission) {
                        $validPermissions[] = $permission;
                    } else {
                        $this->command->warn("  ⚠ Permission '{$permissionName}' not found for role {$roleName}");
                    }
                }

                $role->syncPermissions($validPermissions);
                $this->command->info("  ✓ Assigned " . count($validPermissions) . " permissions to {$roleName}");
            }
        }

        // Clear the cache after creating roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✅ Roles created successfully!');
    }
}
