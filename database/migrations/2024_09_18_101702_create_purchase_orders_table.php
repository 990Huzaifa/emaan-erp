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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->string('order_code');
            $table->date('order_date');
            $table->date('due_date');
            $table->string('terms_of_payment')->nullable();
            $table->string('remarks')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->decimal('delivery_cost', 25, 2)->default(0.00);
            $table->decimal('total_tax', 25, 2)->default(0.00);
            $table->decimal('total_discount', 25, 2)->default(0.00);
            $table->decimal('total', 25, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
