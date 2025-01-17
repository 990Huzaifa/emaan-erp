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
        'city_id',
        'designation',
        'status',
        'payroll',
        'is_allowance',
        'allowance_cycle',
        'allowance',
        'is_bonus',
        'bonus_cycle',
        'bonus',
        'is_tax',
        'tax_cycle',
        'tax',
        'image',
        'added_by',
    ];
}
