<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pay_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger(column: 'business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->string('slip_no')->unique();
            $table->date('pay_period_start'); // Start date of the pay period
            $table->date('pay_period_end'); // End date of the pay period
            $table->date('issue_date'); // Date the payslip is issued
            $table->decimal('basic_salary', 18, 2); 
            $table->decimal('loan_deduction', 18, 2)->default(0.00); // Loan deductions for this period
            $table->decimal('tax_deduction', 18, 2)->default(0.00); // Tax deductions for this period
            $table->decimal('allowance', 18, 2)->default(0.00); // Allowances added for this period
            $table->decimal('bonus', 18, 2)->default(0.00); // Bonus added for this period
            $table->decimal('net_salary', 18, 2); // Final salary after deductions
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected, 4 = Paid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_slips');
    }
};
