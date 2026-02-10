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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_refund_id')->unique()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('note')->nullable();
            $table->string('gateway')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0); // Total refund amount
            $table->decimal('total_tax', 15, 2)->default(0);    // Total tax refunded
            $table->json('transactions')->nullable();           // Raw transaction details if needed
            $table->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            // We link to local order_item if possible (might be null if item was deleted or special case)
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shopify_line_item_id')->nullable()->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            
            $table->integer('quantity')->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->string('restock_type')->nullable(); // no_restock, cancel, return, etc.
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
    }
};
