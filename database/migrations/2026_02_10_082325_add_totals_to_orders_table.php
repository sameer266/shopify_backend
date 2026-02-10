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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'total_discounts')) {
                $table->decimal('total_discounts', 15, 2)->default(0)->after('total_price');
            }
            if (!Schema::hasColumn('orders', 'total_refunds')) {
                $table->decimal('total_refunds', 15, 2)->default(0)->after('total_discounts');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_discounts', 'total_refunds']);
        });
    }
};
