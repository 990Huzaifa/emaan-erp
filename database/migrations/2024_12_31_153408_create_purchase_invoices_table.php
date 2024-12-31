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
            $table->foreign('grn_id')->references('id')->on('grns')->onDelete('cascade');
            $table->string('po_no');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('additional_charges', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->decimal('discount', 10, 2);
            $table->string('note')->nullable();
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
