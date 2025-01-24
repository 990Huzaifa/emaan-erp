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
        Schema::create('pay_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('policy_code');
            $table->decimal('basic_pay', 20, 2)->default(0.00);
            $table->decimal('loan_limit', 20, 2)->default(0.00);
            $table->integer('bonus_percentage')->default(0.00);
            $table->decimal('allowance',20, 2)->default(0.00);
            $table->decimal('deductions',20, 2)->default(0.00);
            $table->integer('tax_rate')->default(0.00);
            $table->boolean('status')->default(1)->comment('1 = Active, 0 = Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay_policies');
    }
};
