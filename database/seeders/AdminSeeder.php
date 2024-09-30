<?php

namespace Database\Seeders;

use App\Models\User;
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
        User::firstOrCreate(['name' => 'super admin', 'u_code'=> 'Ab1230001', 'email' => 'admin@gmail.com','password' => $pwd, "role"=>'admin',"is_verify"=>1,"status"=>1,"city_id"=>1]);
    }
}
