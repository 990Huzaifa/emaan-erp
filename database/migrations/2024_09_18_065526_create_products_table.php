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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('p_code');
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');
            $table->unsignedBigInteger('sub_category_id');
            $table->foreign('sub_category_id')->references('id')->on('product_sub_categories')->onDelete('cascade');
            $table->unsignedBigInteger('measurement_unit_id');
            $table->foreign('measurement_unit_id')->references('id')->on('measurement_units')->onDelete('cascade');
            $table->unsignedBigInteger('acc_id');
            $table->foreign('acc_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->string('title');
            $table->string('brand_name')->nullable();
            $table->string('sku');
            $table->string('description')->nullable();
            $table->longText('image')->nullable();
            $table->longText('terms_of_payment')->nullable();
            $table->string('added_by');
            $table->decimal('sales_tax_rate',10,2)->default(0.00);
            $table->decimal('purchase_price',10,2)->default(0.00);
            $table->decimal('sale_price',10,2)->default(0.00);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
