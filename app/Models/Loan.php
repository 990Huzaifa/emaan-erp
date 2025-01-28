<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'business_id',
        'loan_amount',
        'remaining_amount',
        'installments',
        'installment_amount',
        'loan_date',
        'status',
    ];
}
