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
            $table->string('email',225)->unique();
            $table->string('u_code',225)->unique();
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone',20)->nullable();
            $table->string('cnic')->nullable();
            $table->string('avatar')->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('joining_date')->nullable();
            $table->string('address')->nullable();
            $table->longText('cnic_images')->nullable();
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id')->references('id')->on('cities')->onUpdate('cascade')->onDelete('cascade');
            $table->longText('setup_code')->nullable();
            $table->longText('setup_code_expiry')->nullable();
            $table->boolean('is_verify')->default(0)->comment('1 = verified, 0 = not-verified');
            $table->boolean('status')->default(1)->comment('1 = Active, 0 = Inactive');
            $table->string('role')->default('user');
            $table->integer('max_business_create')->nullable();
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
