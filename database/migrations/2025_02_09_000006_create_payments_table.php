<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_payment_id')->nullable()->index();
            $table->string('gateway')->nullable();
            $table->string('kind')->nullable(); // sale, refund, etc.
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('processed_at')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
