<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_fulfillment_id')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('tracking_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('created_at_shopify')->nullable();
            $table->timestamp('updated_at_shopify')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillments');
    }
};
