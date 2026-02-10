<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('shopify_order_id')->unique()->index();
            $table->string('order_number')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('financial_status')->nullable()->index(); // paid, pending, refunded, etc.
            $table->string('fulfillment_status')->nullable()->index(); // fulfilled, partial, unfulfilled, null
            $table->string('shipping_status')->nullable()->index();
            $table->boolean('is_paid')->default(false)->index();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('subtotal_price', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
           
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
