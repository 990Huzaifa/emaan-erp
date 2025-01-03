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
        'cnic_front',
        'cnic_back',
        'address',
        'joining_date',
        'city_id',
        'status',
        'designation',
        'added_by',
    ];
}
