<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_items', function (Blueprint $table) {
            $table->decimal('discount_allocation', 15, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('refund_items', function (Blueprint $table) {
            $table->dropColumn('discount_allocation');
        });
    }
};
