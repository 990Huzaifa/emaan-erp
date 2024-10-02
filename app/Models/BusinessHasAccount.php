<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessHasAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'chart_of_account_id',
    ];
}
