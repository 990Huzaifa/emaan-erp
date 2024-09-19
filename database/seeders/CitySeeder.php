<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            ['name' => 'Karachi'],
            ['name' => 'Lahore'],
            ['name' => 'Faisalabad'],
            ['name' => 'Rawalpindi'],
            ['name' => 'Gujranwala'],
            ['name' => 'Peshawar'],
            ['name' => 'Multan'],
            ['name' => 'Hyderabad'],
            ['name' => 'Islamabad'],
            ['name' => 'Quetta'],
            // Add more cities as needed
        ];

        DB::table('cities')->insert($cities);
    }
}
