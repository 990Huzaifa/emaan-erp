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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dn_id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_order_id');
            $table->foreign('dn_id')->references('id')->on('delivery_notes')->onDelete('cascade');
            $table->foreign('sale_order_id')->references('id')->on('sale_orders')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onUpdate('cascade')->onDelete('cascade');
            $table->string('sr_code');
            $table->string('received_by');
            $table->date('received_date');
            $table->string('reason');
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
