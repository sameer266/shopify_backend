<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_adjustment_id')->nullable()->index();
            $table->string('kind')->nullable();
            $table->string('reason')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('order_adjustments');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->json('order_adjustments')->nullable()->after('transactions');
        });
        Schema::dropIfExists('refund_adjustments');
    }
};
