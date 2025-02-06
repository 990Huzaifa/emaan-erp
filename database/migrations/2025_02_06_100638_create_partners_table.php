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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('p_code', 225)->unique();
            $table->string('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unsignedBigInteger('acc_id');
            $table->foreign('acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
            $table->string('phone')->nullable();
            $table->string('cnic')->nullable();
            $table->string('avatar')->nullable();
            $table->longText('cnic_images')->nullable();
            $table->string('address')->nullable();
            $table->enum('gender', ['MALE', 'FEMALE'])->default('MALE');
            $table->boolean('status')->default(1)->comment('1 = Active, 0 = Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
