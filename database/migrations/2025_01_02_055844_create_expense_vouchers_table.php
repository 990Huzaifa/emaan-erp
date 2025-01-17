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
        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unsignedBigInteger('asset_acc_id');
            $table->unsignedBigInteger('expense_acc_id');
            $table->foreign('asset_acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('expense_acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->string('voucher_code')->unique();
            $table->enum('payment_method', ['CASH', 'CHEQUE'])->default('CASH');
            $table->string('cheque_no')->nullable();
            $table->date('cheque_date')->nullable();
            $table->decimal('voucher_amount',25,2)->default(0.00);
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->longText('description')->nullable();
            $table->date('voucher_date');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_vouchers');
    }
};
