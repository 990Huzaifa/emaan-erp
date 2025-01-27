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
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->foreign('partner_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('acc_id'); // Link to COA for tracking payments
            $table->foreign('acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->unsignedBigInteger('business_id'); // Link to COA for tracking payments
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->string('voucher_code')->unique();
            $table->enum('payment_method', ['CASH', 'BANK'])->default('CASH');
            $table->enum('type', ['withdraw', 'deposit'])->default('CASH');
            $table->string('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->date('voucher_date')->nullable();
            $table->decimal('voucher_amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_vouchers');
    }
};
