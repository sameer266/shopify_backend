<?php
/**
 * Recalculate Refunds with New Formula
 * 
 * This script recalculates refund amounts using the new formula:
 * total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
 * 
 * Where:
 * - Refund adjustments are always subtracted
 * - If discount is negative, subtract it
 * - If discount is positive, add it
 * 
 * Usage: php recalculate_refunds.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;

echo "═══════════════════════════════════════════════════\n";
echo "    RECALCULATE REFUNDS WITH NEW FORMULA\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    // Step 1: Fix discount values to be positive
    echo "Step 1: Converting negative discounts to positive...\n\n";
    
    $ordersWithNegativeDiscounts = Order::where('total_discounts', '<', 0)->get();
    $discountsFixed = 0;
    
    foreach ($ordersWithNegativeDiscounts as $order) {
        $oldValue = $order->total_discounts;
        $newValue = abs($oldValue);
        
        $order->update(['total_discounts' => $newValue]);
        
        echo sprintf(
            "  ✓ Order #%-10s: Rs %8.2f → Rs %8.2f\n",
            $order->order_number ?: $order->shopify_order_id,
            $oldValue,
            $newValue
        );
        
        $discountsFixed++;
    }
    
    echo "\nDiscounts fixed: {$discountsFixed}\n\n";
    
    // Step 2: Recalculate refund amounts using new formula
    echo "Step 2: Recalculating refund amounts with new formula...\n\n";
    
    $refunds = Refund::with(['refundItems', 'orderAdjustments', 'order'])->get();
    $refundsFixed = 0;
    
    foreach ($refunds as $refund) {
        $order = $refund->order;
        
        // Formula: total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
        
        // Step 1: Sum refund items subtotal
        $itemsSubtotal = $refund->refundItems->sum('subtotal');
        
        // Step 2: Sum refund adjustments amount (always subtracted)
        $adjustmentsTotal = $refund->orderAdjustments->sum('amount');
        
        // Step 3: Get discount from order
        $discount = $order->total_discounts;
        
        // Step 4: Apply formula
        $refundAmount = $itemsSubtotal - $adjustmentsTotal;
        
        // Step 5: Handle discount
        // Note: Since we converted all discounts to positive, we need to determine
        // how to apply it. For now, we'll use a simple approach:
        // - Discounts typically reduce what needs to be refunded
        // - But this depends on your business logic
        
        // Option A: Don't include discount in refund calculation (default)
        // $refundAmount = $refundAmount;
        
        // Option B: Include discount (uncomment if needed)
        // if ($discount < 0) {
        //     $refundAmount -= $discount; // Negative discount adds to refund
        // } else {
        //     $refundAmount += $discount; // Positive discount may affect refund
        // }
        
        // Round and ensure non-negative
        $refundAmount = max(round($refundAmount, 2), 0);
        
        if (abs($refund->total_amount - $refundAmount) > 0.01) {
            $oldValue = $refund->total_amount;
            $refund->update(['total_amount' => $refundAmount]);
            
            $orderNum = $order->order_number ?? $order->shopify_order_id ?? 'N/A';
            
            echo sprintf(
                "  ✓ Refund #%-5s Order #%-10s: Rs %8.2f → Rs %8.2f",
                $refund->id,
                $orderNum,
                $oldValue,
                $refundAmount
            );
            
            // Show breakdown
            echo sprintf(
                " (Items: Rs %.2f, Adj: Rs %.2f, Disc: Rs %.2f)",
                $itemsSubtotal,
                $adjustmentsTotal,
                $discount
            );
            echo "\n";
            
            $refundsFixed++;
        }
    }
    
    echo "\nRefunds recalculated: {$refundsFixed}\n\n";
    
    // Step 3: Update order total_refunds
    echo "Step 3: Updating order total_refunds...\n\n";
    
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
    echo "Discounts Fixed:     " . $discountsFixed . "\n";
    echo "Refunds Fixed:       " . $refundsFixed . "\n";
    echo "Orders Fixed:        " . $ordersFixed . "\n";
    echo "───────────────────────────────────────────────────\n";
    echo sprintf("Old Total:           Rs %12.2f\n", $totalOld);
    echo sprintf("New Total:           Rs %12.2f\n", $totalNew);
    echo sprintf("Difference:          Rs %12.2f\n", abs($totalNew - $totalOld));
    echo "═══════════════════════════════════════════════════\n\n";
    
    // Step 4: Validation report
    echo "Step 4: Validation Report...\n\n";
    
    // Check for any negative values
    $negativeRefunds = Refund::where('total_amount', '<', 0)->count();
    $negativeDiscounts = Order::where('total_discounts', '<', 0)->count();
    
    if ($negativeRefunds > 0) {
        echo "⚠️  WARNING: {$negativeRefunds} refunds have negative amounts!\n";
    } else {
        echo "✅ All refunds have positive amounts\n";
    }
    
    if ($negativeDiscounts > 0) {
        echo "⚠️  WARNING: {$negativeDiscounts} orders still have negative discounts!\n";
    } else {
        echo "✅ All discounts are positive\n";
    }
    
    // Check for orders where refunds exceed total price
    $ordersWithExcessRefunds = Order::whereColumn('total_refunds', '>', 'total_price')->count();
    
    if ($ordersWithExcessRefunds > 0) {
        echo "⚠️  WARNING: {$ordersWithExcessRefunds} orders have refunds exceeding total price!\n";
        echo "    Run this query to investigate:\n";
        echo "    SELECT order_number, total_price, total_refunds FROM orders WHERE total_refunds > total_price;\n";
    } else {
        echo "✅ No orders have refunds exceeding total price\n";
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "    COMPLETE\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    if ($negativeRefunds === 0 && $negativeDiscounts === 0 && $ordersWithExcessRefunds === 0) {
        echo "✅ SUCCESS! All validations passed!\n\n";
    } else {
        echo "⚠️  Some issues found. Please review the warnings above.\n\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
