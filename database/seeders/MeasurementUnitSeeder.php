<?php

namespace Database\Seeders;

use App\Models\MeasurementUnit;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MeasurementUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'kg',
                'slug' => 'Kilogram',
            ],
            [
                'name' => 'lb',
                'slug' => 'Pound',
            ],
            [
                'name' => 'in',
                'slug' => 'Inch',
            ],
            [
                'name' => 'ft',
                'slug' => 'Foot',
            ],
            [
                'name' => 'm',
                'slug' => 'Meter',
            ],
            [
                'name' => 'cm',
                'slug' => 'Centimeter',
            ],
            [
                'name' => 'piece',
                'slug' => 'Piece',
            ],
            [
                'name' => 'pallet',
                'slug' => 'Pallet',
            ],
            [
                'name' => 'box',
                'slug' => 'Box',
            ],
            [
                'name' => 'bottle',
                'slug' => 'Bottle',
            ],
        ];

        foreach ($data as $item) {
            MeasurementUnit::firstOrCreate(
                ['slug' => $item['slug']], 
                ['name' => $item['name']] 
            );
        }
    }

}
