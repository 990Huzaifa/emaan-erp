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
            'edit profile',
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
            'list purchase orders',
            'view purchase orders',
            'create purchase orders',
            'edit purchase orders',
            'approve purchase orders',
            'delete purchase orders',
            
            'list sale orders',
            'view sale orders',
            'create sale orders',
            'edit sale orders',
            'approve sale orders',
            'delete sale orders',
            'special approve sale orders',

            'list goods received notes',
            'view goods received notes',
            'create goods received notes',
            'edit goods received notes',
            'approve goods received notes',
            'reverse goods received notes',

            'list purchase quotations',
            'view purchase quotations',
            'create purchase quotations',
            'edit purchase quotations',
            'approve purchase quotations',
            'list inventory detail',
            'view inventory detail',
            'list transaction',
            'view transaction',
            'create purchase voucher',
            'view purchase voucher',
            'list purchase voucher',
            'edit purchase voucher',
            'list ledger',
            'list advance ledger',
            'list measurement unit',
            'view measurement unit',
            'create measurement unit',
            'edit measurement unit',
            
            'create sale voucher',
            'view sale voucher',
            'list sale voucher',
            'edit sale voucher',
            'approve sale voucher',
            

            'create purchase invoice',
            'view purchase invoice',
            'list purchase invoice',
            'edit purchase invoice',
            'approve purchase invoice',

            'create purchase return',
            'view purchase return',
            'list purchase return',
            'edit purchase return',
            'approve purchase return',

            'create purchase return voucher',
            'view purchase return voucher',
            'list purchase return voucher',
            'edit purchase return voucher',
            'approve purchase return voucher',

            'create sale return',
            'view sale return',
            'list sale return',
            'edit sale return',
            'approve sale return',

            'create sale return voucher',
            'view sale return voucher',
            'list sale return voucher',
            'edit sale return voucher',
            'approve sale return voucher',

            'list sale receipt',
            'view sale receipt',
            'create sale receipt',
            'edit sale receipt',
            'approve sale receipt',

            'create employee',
            'view employee',
            'list employee',
            'edit employee',
            'approve employee',

            'list sale quotations',
            'view sale quotations',
            'create sale quotations',
            'edit sale quotations',
            'approve sale quotations',

            'list delivery notes',
            'view delivery notes',
            'create delivery notes',
            'edit delivery notes',
            'approve delivery notes',
            'reverse delivery notes',

            'list expense voucher',
            'view expense voucher',
            'create expense voucher',
            'edit expense voucher',
            'approve expense voucher',

            'list salary voucher',
            'view salary voucher',
            'create salary voucher',
            'edit salary voucher',
            'approve salary voucher',

            'list department',
            'view department',
            'create department',
            'edit department',

            'list designation',
            'view designation',
            'create designation',
            'edit designation',

            'list pay policy',
            'view pay policy',
            'create pay policy',
            'edit pay policy',
            'approve pay policy',

            'list pay slip',
            'view pay slip',
            'create pay slip',
            'edit pay slip',
            'approve pay slip',

            'list partner',
            'view partner',
            'create partner',
            'edit partner',
            'approve partner',
            
            'list journal voucher',
            'view journal voucher',
            'create journal voucher',
            'edit journal voucher',
            'approve journal voucher',
            
            'list loan',
            'view loan',
            'create loan',
            'edit loan',
            'approve loan',

            'list loan voucher',
            'view loan voucher',
            'create loan voucher',
            'edit loan voucher',
            'approve loan voucher',

            'sales summary',
            'purchase summary',
            'party sales summary',
            'party purchase summary',
            'invenoty report',
            'financial report',
            'sales chart',
            'balance sheet',
            'customer balance',
            'vendor balance',
            'cashnbank balance',
        ];

        // Loop through each permission and create it
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }
    }
}
