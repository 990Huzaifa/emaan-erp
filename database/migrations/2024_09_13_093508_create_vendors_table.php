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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('v_code');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities')->onUpdate('cascade')->onDelete('cascade');
            $table->string('telephone')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->longText('address')->nullable();
            $table->decimal('opening_credit_balance',15,2)->default(0.00);
            $table->decimal('opening_debit_balance',15,2)->default(0.00);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
