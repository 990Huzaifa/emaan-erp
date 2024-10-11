<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class COASeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'code' => '1',
                'parent_code' => '0',
                'name' => 'ASSETS',
                'level1' => '1',
                'level2' => '0',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '2',
                'parent_code' => '0',
                'name' => 'LIABILITIES',
                'level1' => '2',
                'level2' => '0',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '3',
                'parent_code' => '0',
                'name' => 'EQUITY',
                'level1' => '3',
                'level2' => '0',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '4',
                'parent_code' => '0',
                'name' => 'EXPENSES',
                'level1' => '4',
                'level2' => '0',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '5',
                'parent_code' => '0',
                'name' => 'REVENUE',
                'level1' => '5',
                'level2' => '0',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-1',
                'parent_code' => '1',
                'name' => 'CASH',
                'level1' => '1',
                'level2' => '1',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-1-1',
                'parent_code' => '1-1',
                'name' => 'PETTY CASH',
                'level1' => '1',
                'level2' => '1',
                'level3' => '1',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-2',
                'parent_code' => '1',
                'name' => 'BANK',
                'level1' => '1',
                'level2' => '2',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-3',
                'parent_code' => '1',
                'name' => 'INVENTORY',
                'level1' => '1',
                'level2' => '3',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-4',
                'parent_code' => '1',
                'name' => 'ACCOUNTS RECEIVABLE',
                'level1' => '1',
                'level2' => '4',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '1-4-1',
                'parent_code' => '1-4',
                'name' => 'CUSTOMERS',
                'level1' => '1',
                'level2' => '4',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '2-1',
                'parent_code' => '2',
                'name' => 'ACCOUNTS PAYABLE',
                'level1' => '2',
                'level2' => '1',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '2-1-1',
                'parent_code' => '2-1',
                'name' => 'VENDORS',
                'level1' => '2',
                'level2' => '1',
                'level3' => '1',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '2-1-1-1',
                'parent_code' => '2-1-1',
                'name' => 'HYDERABAD',
                'ref_id' => '1',
                'level1' => '2',
                'level2' => '1',
                'level3' => '1',
                'level4' => '1',
                'level5' => '0',
            ],
            [
                'code' => '4-1',
                'parent_code' => '4',
                'name' => 'PURCHASE ORDERS',
                'level1' => '4',
                'level2' => '1',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
            [
                'code' => '5-1',
                'parent_code' => '5',
                'name' => 'SALE ORDERS',
                'level1' => '5',
                'level2' => '1',
                'level3' => '0',
                'level4' => '0',
                'level5' => '0',
            ],
        ];
        foreach ($data as $item) {
            ChartOfAccount::firstOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'parent_code' => $item['parent_code'],
                    'level1' => $item['level1'],
                    'level2' => $item['level2'],
                    'level3' => $item['level3'],
                    'level4' => $item['level4'],
                    'level5' => $item['level5'],
                ]
            );
        }
    }
}
