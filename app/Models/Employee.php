<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'e_code',
        'phone',
        'business_id',
        'acc_id',
        'cnic',
        'cnic_images',
        'address',
        'joining_date',
        'resign_date',
        'city_id',
        'designation_id',
        'department_id',
        'status',
        'image',
        'added_by',
    ];
}
