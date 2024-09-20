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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->string('email',225)->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone',20)->nullable();
            $table->string('cnic');
            $table->string('avatar')->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('joining_date')->nullable();
            $table->string('address')->nullable();
            $table->string('city');
            $table->string('status')->default(1)->comment('1 = Active, 0 = Inactive');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
