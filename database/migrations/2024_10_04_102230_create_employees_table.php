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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unsignedBigInteger('acc_id');
            $table->foreign('acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->string('designation');
            $table->string('e_code');
            $table->string('email');
            $table->string('phone');
            $table->string('cnic');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->longText('address');
            $table->longText('image')->nullable();
            $table->longText('cnic_images')->nullable();
            $table->decimal('payroll', 18, 2);
            $table->boolean('is_allowance')->default(0)->comment('1 = Yes, 0 = No');
            $table->enum('allowance_cycle', ['monthly', 'yearly'])->nullable();
            $table->decimal('allowance', 18, 2)->default(0.00);
            $table->boolean('is_bonus')->default(0)->comment('1 = Yes, 0 = No');
            $table->enum('bonus_cycle', ['monthly', 'yearly'])->nullable();
            $table->decimal('bonus', 18, 2)->default(0.00);
            $table->boolean('is_tax')->default(0)->comment('1 = Yes, 0 = No');
            $table->enum('tax_cycle', ['monthly', 'yearly'])->nullable();
            $table->decimal('tax', 18, 2)->default(0.00);
            $table->boolean('is_loan')->default(0)->comment('1 = Yes, 0 = No');
            $table->decimal('loan', 18, 2)->default(0.00); 
            $table->string('added_by');
            $table->date('joining_date');
            $table->boolean('status')->default(1)->comment('1 = Working, 0 = Resigned');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
