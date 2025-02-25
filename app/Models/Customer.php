<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city_id',
        'acc_id',
        'sales_tax_rate',
        'cnic',
        'logo',
        'email',
        'c_code',
        'business_id',
        'website',
        'address',
        'telephone',
        'mobile',
        'credit_limit',
    ];
}
