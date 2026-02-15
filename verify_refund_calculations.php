<?php
/**
 * Verify Refund Calculations Match Shopify
 * 
 * This script verifies that:
 * 1. Each refund.total_amount = SUM(refund_items.subtotal) - SUM(adjustments)
 * 2. Order.total_refunds = SUM(refunds.total_amount)
 * 3. Total matches Shopify's Net Returns: Rs 23,731.00
 * 
 * Usage: php verify_refund_calculations.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;

echo "═══════════════════════════════════════════════════\n";
echo "    VERIFY REFUND CALCULATIONS\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    // Get all orders with refunds
    $orders = Order::has('refunds')
        ->with(['refunds.refundItems', 'refunds.orderAdjustments'])
        ->get();
    
    $grandTotalRefunds = 0;
    $issuesFound = 0;
    
    echo "Checking " . $orders->count() . " orders with refunds...\n\n";
    
    foreach ($orders as $order) {
        $orderDisplay = $order->order_number ?: $order->shopify_order_id;
        
        echo "─────────────────────────────────────────────────\n";
        echo "Order: {$orderDisplay}\n";
        echo "─────────────────────────────────────────────────\n";
        
        $calculatedOrderTotal = 0;
        
        foreach ($order->refunds as $refund) {
            // Calculate what the refund SHOULD be
            $itemsSubtotal = $refund->refundItems->sum('subtotal');
            $adjustmentsTotal = $refund->orderAdjustments->sum('amount');
            $expectedAmount = $itemsSubtotal - $adjustmentsTotal;
            $expectedAmount = max(round($expectedAmount, 2), 0);
            
            $storedAmount = $refund->total_amount;
            $difference = abs($expectedAmount - $storedAmount);
            
            echo "\n  Refund #{$refund->id}:\n";
            echo "    Items Subtotal:      Rs " . number_format($itemsSubtotal, 2) . "\n";
            echo "    Adjustments:         Rs " . number_format($adjustmentsTotal, 2) . "\n";
            echo "    Expected Total:      Rs " . number_format($expectedAmount, 2) . "\n";
            echo "    Stored Total:        Rs " . number_format($storedAmount, 2) . "\n";
            echo "    Difference:          Rs " . number_format($difference, 2);
            
            if ($difference > 0.01) {
                echo " ❌ MISMATCH!\n";
                $issuesFound++;
            } else {
                echo " ✓\n";
            }
            
            // Show individual items
            if ($refund->refundItems->count() > 0) {
                echo "\n    Refund Items:\n";
                foreach ($refund->refundItems as $item) {
                    echo sprintf(
                        "      - Qty: %d, Subtotal: Rs %s, Discount: Rs %s\n",
                        $item->quantity,
                        number_format($item->subtotal, 2),
                        number_format($item->discount_allocation, 2)
                    );
                }
            }
            
            if ($refund->orderAdjustments->count() > 0) {
                echo "\n    Adjustments:\n";
                foreach ($refund->orderAdjustments as $adj) {
                    echo sprintf(
                        "      - %s: Rs %s (%s)\n",
                        $adj->kind ?? 'Unknown',
                        number_format($adj->amount, 2),
                        $adj->reason ?? 'No reason'
                    );
                }
            }
            
            $calculatedOrderTotal += $expectedAmount;
        }
        
        // Verify order total
        echo "\n  Order Total Verification:\n";
        echo "    Calculated:          Rs " . number_format($calculatedOrderTotal, 2) . "\n";
        echo "    Stored (DB):         Rs " . number_format($order->total_refunds, 2) . "\n";
        
        $orderDiff = abs($calculatedOrderTotal - $order->total_refunds);
        echo "    Difference:          Rs " . number_format($orderDiff, 2);
        
        if ($orderDiff > 0.01) {
            echo " ❌ MISMATCH!\n";
            $issuesFound++;
        } else {
            echo " ✓\n";
        }
        
        $grandTotalRefunds += $order->total_refunds;
        
        echo "\n";
    }
    
    echo "═══════════════════════════════════════════════════\n";
    echo "    SUMMARY\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "Total Orders:            " . $orders->count() . "\n";
    echo "Issues Found:            " . $issuesFound . "\n";
    echo "───────────────────────────────────────────────────\n";
    echo sprintf("Your Total Refunds:      Rs %s\n", number_format($grandTotalRefunds, 2));
    echo sprintf("Shopify Net Returns:     Rs %s\n", number_format(23731.00, 2));
    echo sprintf("Difference:              Rs %s\n", number_format(abs($grandTotalRefunds - 23731.00), 2));
    echo "═══════════════════════════════════════════════════\n\n";
    
    if ($issuesFound === 0 && abs($grandTotalRefunds - 23731.00) < 1.00) {
        echo "✅ SUCCESS! All calculations match Shopify!\n\n";
        exit(0);
    } else {
        echo "⚠️  Issues found or total doesn't match Shopify.\n";
        echo "   Run: php artisan sync:orders\n";
        echo "   to recalculate with the corrected formula.\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
