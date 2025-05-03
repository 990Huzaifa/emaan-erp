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
        Schema::create('goods_receive_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onUpdate('cascade')->onDelete('cascade');
            $table->string('grn_code')->unique();
            $table->date('grn_date');
            $table->string('received_by');
            $table->text('terms_of_payment')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('delivery_cost',25,2)->default(0.00);
            $table->decimal('total_tax',25,2)->default(0.00);
            $table->decimal('total_discount',25,2)->default(0.00);
            $table->decimal('total',25,2)->default(0.00);
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('good_receive_notes');
    }
};
