<?php

namespace Database\Seeders;

use App\Models\Admin;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pwd = Hash::make('admin');
        Admin::firstOrCreate(['name' => 'super admin', 'email' => 'admin@gmail.com','password' => $pwd,]);
    }
}
