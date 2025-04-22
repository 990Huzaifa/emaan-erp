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
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->string('order_code');
            $table->date('order_date');
            $table->date('due_date');
            $table->string('terms_of_payment')->nullable();
            $table->string('remarks')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->decimal('delivery_cost', 20, 2)->default(0.00);
            $table->decimal('total_tax', 20, 2)->default(0.00);
            $table->bigInteger('total_discount')->default(0);
            $table->decimal('total', 20, 2)->default(0.00);
            $table->boolean('special')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_orders');
    }
};
