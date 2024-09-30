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
            $table->string('city');
            $table->string('telephone')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->longText('address')->nullable();
            $table->decimal('opening_balance',8,2)->default(0);
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
