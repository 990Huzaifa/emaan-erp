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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('quantity');
<<<<<<< HEAD
            $table->decimal('unit_price', 20, 2);
            $table->boolean('discount_in_percentage')->default(0);
            $table->decimal('discount', 20, 2);
            $table->decimal('total_price', 20, 2);
=======
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total_price', 18, 2);
>>>>>>> 803cd829cb9ef0bee955b0a1747a61b6d788ce57
            $table->decimal('tax', 20, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
