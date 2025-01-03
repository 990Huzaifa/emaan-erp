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
            $table->decimal('payroll', 8, 2);
            $table->longText('image')->nullable();
            $table->longText('cnic_images')->nullable();
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
