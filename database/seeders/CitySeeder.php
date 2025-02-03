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
            ['name' => 'Bahawalpur'],
            ['name' => 'Chakwal'],
            ['name' => 'Faisalabad'],
            ['name' => 'Gujranwala'],
            ['name' => 'Gujrat'],
            ['name' => 'Hyderabad'],
            ['name' => 'Islamabad'],
            ['name' => 'Jhang'],
            ['name' => 'Jhelum'],
            ['name' => 'Karachi'],
            ['name' => 'Kasur'],
            ['name' => 'Larkana'],
            ['name' => 'Lahore'],
            ['name' => 'Mianwali'],
            ['name' => 'Mardan'],
            ['name' => 'Multan'],
            ['name' => 'Nawabshah'],
            ['name' => 'Okara'],
            ['name' => 'Peshawar'],
            ['name' => 'Quetta'],
            ['name' => 'Rawalpindi'],
            ['name' => 'Sialkot'],
            ['name' => 'Sargodha'],
            ['name' => 'Sukkur'],
            ['name' => 'Sahiwal'],
            ['name' => 'Thatta'],
            ['name' => 'Umarkot'],
            ['name' => 'Wah Cantonment'],
            ['name' => 'Wazirabad'],
            // Add more cities as needed
        ];

        DB::table('cities')->insert($cities);
    }
}
