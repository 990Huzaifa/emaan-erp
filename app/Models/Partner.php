<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'p_code',
        'business_id',
        'acc_id',
        'city_id',
        'phone',
        'address',
        'avatar',
        'cnic',
        'gender',
        'cnic_images',
        'status',
    ];
}
