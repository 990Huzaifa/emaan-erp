<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'list users',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'list customers',
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            'list products',
            'view products',
            'create products',
            'edit products',
            'delete products',
            'list permissions',
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',
        ];

        // Loop through each permission and create it
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }
    }
}
