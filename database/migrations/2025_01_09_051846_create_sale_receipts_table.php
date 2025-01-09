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
        Schema::create('sale_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no');
            $table->date('receipt_date');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('dn_id');
            $table->foreign('dn_id')->references('id')->on('delivery_notes')->onDelete('cascade');
            $table->string('so_no');
            $table->string('terms_of_payment')->nullable();
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected, 4 = Paid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_receipts');
    }
};
