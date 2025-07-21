<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Story permissions
        Permission::create(['name' => 'list stories']);
        Permission::create(['name' => 'show stories']);
        Permission::create(['name' => 'create stories']);
        Permission::create(['name' => 'update stories']);
        Permission::create(['name' => 'delete stories']);
        Permission::create(['name' => 'publish stories']);
        Permission::create(['name' => 'unpublish stories']);

        // Category permissions
        Permission::create(['name' => 'list categories']);
        Permission::create(['name' => 'show categories']);
        Permission::create(['name' => 'create categories']);
        Permission::create(['name' => 'update categories']);
        Permission::create(['name' => 'delete categories']);

        // Tag permissions
        Permission::create(['name' => 'list tags']);
        Permission::create(['name' => 'show tags']);
        Permission::create(['name' => 'create tags']);
        Permission::create(['name' => 'update tags']);
        Permission::create(['name' => 'delete tags']);

        // User management permissions
        Permission::create(['name' => 'list users']);
        Permission::create(['name' => 'show users']);
        Permission::create(['name' => 'create users']);
        Permission::create(['name' => 'update users']);
        Permission::create(['name' => 'delete users']);

        // Member management permissions
        Permission::create(['name' => 'list members']);
        Permission::create(['name' => 'show members']);
        Permission::create(['name' => 'create members']);
        Permission::create(['name' => 'update members']);
        Permission::create(['name' => 'delete members']);
        Permission::create(['name' => 'activate members']);
        Permission::create(['name' => 'suspend members']);

        // Role & Permission management
        Permission::create(['name' => 'list roles']);
        Permission::create(['name' => 'show roles']);
        Permission::create(['name' => 'create roles']);
        Permission::create(['name' => 'update roles']);
        Permission::create(['name' => 'delete roles']);
        Permission::create(['name' => 'list permissions']);
        Permission::create(['name' => 'show permissions']);

        // Analytics permissions
        Permission::create(['name' => 'view analytics']);
        Permission::create(['name' => 'view member analytics']);
        Permission::create(['name' => 'view story analytics']);
        Permission::create(['name' => 'export analytics']);

        // System permissions
        Permission::create(['name' => 'view logs']);
        Permission::create(['name' => 'manage settings']);
        Permission::create(['name' => 'backup system']);
        Permission::create(['name' => 'restore system']);
    }
}
