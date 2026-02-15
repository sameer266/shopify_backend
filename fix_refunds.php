<?php
/**
 * Fix Refund Calculations Script - FINAL CORRECTED VERSION
 * 
 * This script properly calculates refunds using line items + adjustments (WITHOUT tax)
 * to match Shopify's net returns calculation.
 * 
 * Usage: php fix_refunds.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════\n";
echo "    FIXING REFUND CALCULATIONS (FINAL FIX)\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    // Step 1: Recalculate refund.total_amount from line items and adjustments
    echo "Step 1: Recalculating refund amounts from line items & adjustments...\n\n";
    
    $refunds = Refund::with(['refundItems', 'orderAdjustments'])->get();
    $refundsFixed = 0;
    
    foreach ($refunds as $refund) {
        // Calculate from line items (subtotal only, no tax)
        $totalAmount = $refund->refundItems->sum('subtotal');
        
        // Add adjustments (amount only, no tax)
        $totalAmount += $refund->orderAdjustments->sum('amount');
        
        $totalAmount = round($totalAmount, 2);
        
        if (abs($refund->total_amount - $totalAmount) > 0.01) {
            $oldValue = $refund->total_amount;
            $refund->update(['total_amount' => $totalAmount]);
            
            echo sprintf(
                "  ✓ Refund #%-5s Order #%-10s: Rs %8.2f → Rs %8.2f\n",
                $refund->id,
                $refund->order->order_number ?? $refund->order->shopify_order_id ?? 'N/A',
                $oldValue,
                $totalAmount
            );
            $refundsFixed++;
        }
    }
    
    echo "\nRefunds updated: {$refundsFixed}\n\n";
    
    // Step 2: Update order.total_refunds
    echo "Step 2: Updating order total_refunds...\n\n";
    
    $orders = Order::with('refunds')->get();
    
    $ordersFixed = 0;
    $totalOldAmount = 0;
    $totalNewAmount = 0;

    foreach ($orders as $order) {
        // Sum up total_amount from all refunds for this order
        $totalRefunds = $order->refunds->sum('total_amount');
        $totalRefunds = round($totalRefunds, 2);

        if (abs($order->total_refunds - $totalRefunds) > 0.01) {
            $oldValue = $order->total_refunds;
            
            $order->update(['total_refunds' => max($totalRefunds, 0)]);
            
            $totalOldAmount += $oldValue;
            $totalNewAmount += $totalRefunds;
            $ordersFixed++;

            echo sprintf(
                "  ✓ Order #%-10s: Rs %10.2f → Rs %10.2f (Diff: Rs %10.2f)\n",
                $order->order_number ?: $order->shopify_order_id,
                $oldValue,
                $totalRefunds,
                $totalRefunds - $oldValue
            );
        }
    }

    echo "\n═══════════════════════════════════════════════════\n";
    echo "    SUMMARY\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "Refunds Recalculated:    " . $refundsFixed . "\n";
    echo "Orders Updated:          " . $ordersFixed . "\n";
    echo "Orders Unchanged:        " . ($orders->count() - $ordersFixed) . "\n";
    echo "───────────────────────────────────────────────────\n";
    echo sprintf("Old Total Refunds:       Rs %12.2f\n", $totalOldAmount);
    echo sprintf("New Total Refunds:       Rs %12.2f\n", $totalNewAmount);
    echo sprintf("Difference:              Rs %12.2f\n", $totalNewAmount - $totalOldAmount);
    echo sprintf("Expected (Shopify):      Rs %12.2f\n", 23731.00);
    echo "═══════════════════════════════════════════════════\n\n";

    if (abs($totalNewAmount - 23731.00) < 1) {
        echo "✅ SUCCESS! Total matches Shopify exactly!\n";
        echo "   Your net returns now show: Rs 23,731.00 ✅\n\n";
    } elseif ($ordersFixed > 0 || $refundsFixed > 0) {
        echo "✅ Calculations updated!\n";
        echo "   New total: Rs " . number_format($totalNewAmount, 2) . "\n";
        echo "   Check if this matches your Shopify reports.\n\n";
    } else {
        echo "ℹ️  No changes needed. All refunds already correct.\n\n";
    }

} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}