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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->decimal('loan_amount', 18, 2); // Total loan amount
            $table->decimal('remaining_balance', 18, 2); // Remaining loan balance
            $table->decimal('monthly_installment', 18, 2); // Installment amount deducted monthly
            $table->date('start_date'); // Loan start date
            $table->date('end_date')->nullable(); // Expected loan end date
            $table->enum('status', ['active', 'paid'])->default('active'); // Loan status
            $table->longText('remarks')->nullable(); // Optional remarks about the loan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
