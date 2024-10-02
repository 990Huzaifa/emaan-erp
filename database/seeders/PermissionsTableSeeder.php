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
            'list businesses',
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
            'list vendors',
            'view vendors',
            'create vendors',
            'edit vendors',
            'delete vendors',
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
            'edit mail',
            'view mail',
            'create chart of account',
            'view chart of account',
            'edit chart of account',
            'list chart of account',
            'list product category',
            'create product category',
            'view product category',
            'delete product category',
            'edit product category',
            'list product sub category',
            'create product sub category',
            'view product sub category',
            'delete product sub category',
            'edit product sub category',
        ];

        // Loop through each permission and create it
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }
    }
}
