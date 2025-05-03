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
        Schema::create('goods_receive_note_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goods_receive_note_id');
            $table->foreign('goods_receive_note_id')->references('id')->on('goods_receive_notes')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('receive');
<<<<<<< HEAD
            $table->decimal('billed', 8, 2);
            $table->decimal('purchase_unit_price', 25, 2);
            $table->decimal('sale_unit_price', 25, 2);
            $table->decimal('discount', 25, 2);
            $table->boolean('discount_in_percentage')->default(0);
            $table->decimal('total_price', 25, 2);
            $table->decimal('tax', 25, 2)->default(0.00);
=======
            $table->decimal('billed', 18, 2);
            $table->decimal('purchase_unit_price', 20, 2);
            $table->decimal('sale_unit_price', 20, 2);
            $table->decimal('total_price', 20, 2);
            $table->decimal('tax', 20, 2)->default(0.00);
>>>>>>> 803cd829cb9ef0bee955b0a1747a61b6d788ce57
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('good_receive_note_items');
    }
};
