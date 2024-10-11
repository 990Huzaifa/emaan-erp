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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('c_code');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('acc_id');
            $table->foreign('acc_id')->references('id')->on('chart_of_accounts')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities')->onUpdate('cascade')->onDelete('cascade');
            $table->string('telephone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('cnic');
            $table->string('logo')->nullable();
            $table->longText('address')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
