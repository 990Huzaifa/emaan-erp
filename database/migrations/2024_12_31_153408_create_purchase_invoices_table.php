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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no');
            $table->date('invoice_date');
            $table->unsignedBigInteger('business_id');
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->unsignedBigInteger('vendor_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->unsignedBigInteger('grn_id');
            $table->foreign('grn_id')->references('id')->on('goods_receive_notes')->onDelete('cascade');
            $table->string('po_no');
            $table->string('terms_of_payment')->nullable();
<<<<<<< HEAD
            $table->string('remarks')->nullable();
            $table->decimal('delivery_cost', 25, 2)->default(0.00);
            $table->decimal('total_tax', 25, 2)->default(0.00);
            $table->decimal('total_discount', 25, 2)->default(0.00);
            $table->decimal('total', 25, 2)->default(0.00);
=======
            $table->decimal('total',28,2);
>>>>>>> 803cd829cb9ef0bee955b0a1747a61b6d788ce57
            $table->string('status')->default(0)->comment('0 = Pending, 1 = Approved, 2 = Rejected, 4 = Paid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
