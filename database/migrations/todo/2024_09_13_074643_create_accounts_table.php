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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('chart_of_account_id');
            $table->foreign('chart_of_account_id')->references('chart_of_accounts')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('logo')->nullable();
            $table->longText('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
