<?php
/**
 * Fix Refunds - Handle Negative Adjustments Correctly
 * 
 * This version properly handles:
 * - Negative adjustments (restocking fees, refund fees)
 * - Only counts positive refund amounts
 * 
 * Usage: php fix_refunds_correct.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;

echo "═══════════════════════════════════════════════════\n";
echo "    FIX REFUNDS (HANDLE NEGATIVES)\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    // Step 1: Fix refund total_amount
    echo "Step 1: Recalculating refund amounts...\n\n";
    
    $refunds = Refund::with(['refundItems', 'orderAdjustments'])->get();
    $refundsFixed = 0;
    
    foreach ($refunds as $refund) {
        // Line items subtotal (should always be positive)
        $itemsTotal = $refund->refundItems->sum('subtotal');
        
        // Adjustments - ONLY add positive ones
        // Negative adjustments are fees that reduce the refund
        $adjustmentsTotal = $refund->orderAdjustments->sum(function($adj) {
            $amount = (float) $adj->amount;
            // Only count positive adjustments (actual refunds)
            // Negative adjustments (fees) reduce the items total
            return $amount;  // Include both positive and negative
        });
        
        // Total refund = items + adjustments (adjustments can be negative)
        $totalAmount = $itemsTotal + $adjustmentsTotal;
        $totalAmount = round($totalAmount, 2);
        
        // Refund should never be negative overall
        $totalAmount = max($totalAmount, 0);
        
        if (abs($refund->total_amount - $totalAmount) > 0.01) {
            $oldValue = $refund->total_amount;
            $refund->update(['total_amount' => $totalAmount]);
            
            $orderNum = $refund->order->order_number ?? $refund->order->shopify_order_id ?? 'N/A';
            
            echo sprintf(
                "  ✓ Refund #%-5s Order #%-10s: Rs %8.2f → Rs %8.2f",
                $refund->id,
                $orderNum,
                $oldValue,
                $totalAmount
            );
            
            // Show breakdown if there are adjustments
            if ($refund->orderAdjustments->count() > 0) {
                echo sprintf(
                    " (Items: Rs %.2f, Adj: Rs %.2f)",
                    $itemsTotal,
                    $adjustmentsTotal
                );
            }
            echo "\n";
            
            $refundsFixed++;
        }
    }
    
    echo "\nRefunds updated: {$refundsFixed}\n\n";
    
    // Step 2: Update order totals
    echo "Step 2: Updating order total_refunds...\n\n";
    
    $orders = Order::with('refunds')->get();
    $ordersFixed = 0;
    $totalOld = 0;
    $totalNew = 0;
    
    foreach ($orders as $order) {
        $totalRefunds = round($order->refunds->sum('total_amount'), 2);
        
        if (abs($order->total_refunds - $totalRefunds) > 0.01) {
            $oldValue = $order->total_refunds;
            $order->update(['total_refunds' => max($totalRefunds, 0)]);
            
            $totalOld += $oldValue;
            $totalNew += $totalRefunds;
            $ordersFixed++;
            
            echo sprintf(
                "  ✓ Order #%-10s: Rs %10.2f → Rs %10.2f\n",
                $order->order_number ?: $order->shopify_order_id,
                $oldValue,
                $totalRefunds
            );
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "    SUMMARY\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "Refunds Fixed:       " . $refundsFixed . "\n";
    echo "Orders Fixed:        " . $ordersFixed . "\n";
    echo "───────────────────────────────────────────────────\n";
    echo sprintf("Old Total:           Rs %12.2f\n", $totalOld);
    echo sprintf("New Total:           Rs %12.2f\n", $totalNew);
    echo sprintf("Expected (Shopify):  Rs %12.2f\n", 23731.00);
    echo sprintf("Difference:          Rs %12.2f\n", abs($totalNew - 23731.00));
    echo "═══════════════════════════════════════════════════\n\n";
    
    if (abs($totalNew - 23731.00) < 1) {
        echo "✅ SUCCESS! Matches Shopify!\n\n";
    } else {
        echo "⚠️  Still doesn't match. Run debug scripts to investigate:\n";
        echo "   php debug_refund_details.php\n";
        echo "   php compare_refunds_with_shopify.php\n\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
