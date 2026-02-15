<?php
/**
 * Validate Refund Calculations
 * 
 * This script validates that refund calculations are correct by comparing
 * database values with manual calculations using the formula.
 * 
 * Usage: php validate_refunds.php [order_number]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;

echo "═══════════════════════════════════════════════════\n";
echo "    REFUND CALCULATION VALIDATOR\n";
echo "═══════════════════════════════════════════════════\n\n";

// Get order number from command line argument if provided
$orderNumber = $argv[1] ?? null;

try {
    if ($orderNumber) {
        // Validate specific order
        $orders = Order::where('order_number', $orderNumber)
            ->orWhere('shopify_order_id', $orderNumber)
            ->with(['refunds.refundItems', 'refunds.orderAdjustments'])
            ->get();
            
        if ($orders->isEmpty()) {
            echo "❌ Order not found: {$orderNumber}\n";
            exit(1);
        }
    } else {
        // Validate all orders with refunds
        $orders = Order::has('refunds')
            ->with(['refunds.refundItems', 'refunds.orderAdjustments'])
            ->get();
    }
    
    echo "Validating " . $orders->count() . " order(s)...\n\n";
    
    $validCount = 0;
    $invalidCount = 0;
    $tolerance = 0.01; // Rs 0.01 tolerance for floating point rounding
    
    foreach ($orders as $order) {
        $orderDisplay = $order->order_number ?: $order->shopify_order_id;
        
        echo "─────────────────────────────────────────────────\n";
        echo "Order: {$orderDisplay}\n";
        echo "─────────────────────────────────────────────────\n";
        
        // Order level data
        echo "\nOrder Data:\n";
        echo sprintf("  Total Price:     Rs %10.2f\n", $order->total_price);
        echo sprintf("  Total Discounts: Rs %10.2f\n", $order->total_discounts);
        echo sprintf("  Total Refunds:   Rs %10.2f\n", $order->total_refunds);
        
        // Calculate expected total refunds
        $calculatedTotal = 0;
        
        foreach ($order->refunds as $index => $refund) {
            echo "\n  Refund #" . ($index + 1) . " (ID: {$refund->id}):\n";
            
            // Formula components
            $itemsSubtotal = $refund->refundItems->sum('subtotal');
            $adjustmentsTotal = $refund->orderAdjustments->sum('amount');
            $discount = $order->total_discounts;
            
            echo sprintf("    Items Subtotal:      Rs %10.2f\n", $itemsSubtotal);
            echo sprintf("    Adjustments Total:   Rs %10.2f\n", $adjustmentsTotal);
            echo sprintf("    Order Discount:      Rs %10.2f\n", $discount);
            
            // Show individual items
            if ($refund->refundItems->count() > 0) {
                echo "\n    Refund Items:\n";
                foreach ($refund->refundItems as $item) {
                    echo sprintf(
                        "      - Qty: %2d, Subtotal: Rs %8.2f\n",
                        $item->quantity,
                        $item->subtotal
                    );
                }
            }
            
            // Show adjustments
            if ($refund->orderAdjustments->count() > 0) {
                echo "\n    Adjustments:\n";
                foreach ($refund->orderAdjustments as $adj) {
                    echo sprintf(
                        "      - %s: Rs %8.2f (%s)\n",
                        $adj->kind ?? 'Unknown',
                        $adj->amount,
                        $adj->reason ?? 'No reason'
                    );
                }
            }
            
            // Calculate expected amount
            // Formula: total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
            $expectedAmount = $itemsSubtotal - $adjustmentsTotal;
            
            // Note: Discount handling may vary based on business logic
            // Currently not including discount in calculation
            // Uncomment and adjust if needed:
            // if ($discount < 0) {
            //     $expectedAmount -= $discount;
            // } else {
            //     $expectedAmount += $discount;
            // }
            
            $expectedAmount = max(round($expectedAmount, 2), 0);
            $storedAmount = $refund->total_amount;
            $difference = abs($expectedAmount - $storedAmount);
            
            echo "\n    Formula Calculation:\n";
            echo sprintf("    %10.2f - %10.2f = Rs %10.2f\n", 
                $itemsSubtotal, 
                $adjustmentsTotal, 
                $expectedAmount
            );
            echo sprintf("    Stored Amount:       Rs %10.2f\n", $storedAmount);
            echo sprintf("    Difference:          Rs %10.2f ", $difference);
            
            if ($difference <= $tolerance) {
                echo "✅\n";
            } else {
                echo "❌ MISMATCH!\n";
                $invalidCount++;
            }
            
            $calculatedTotal += $expectedAmount;
        }
        
        // Validate order total
        echo "\n  Order Total Validation:\n";
        echo sprintf("    Calculated Total:    Rs %10.2f\n", $calculatedTotal);
        echo sprintf("    Stored Total:        Rs %10.2f\n", $order->total_refunds);
        $orderDifference = abs($calculatedTotal - $order->total_refunds);
        echo sprintf("    Difference:          Rs %10.2f ", $orderDifference);
        
        if ($orderDifference <= $tolerance) {
            echo "✅\n";
            $validCount++;
        } else {
            echo "❌ MISMATCH!\n";
            $invalidCount++;
        }
        
        // Check if refund exceeds order total
        if ($order->total_refunds > $order->total_price) {
            echo "\n  ⚠️  WARNING: Refund exceeds order total!\n";
        }
        
        echo "\n";
    }
    
    echo "═══════════════════════════════════════════════════\n";
    echo "    VALIDATION SUMMARY\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "Total Orders Checked:    " . $orders->count() . "\n";
    echo "Valid Calculations:      " . $validCount . " ✅\n";
    echo "Invalid Calculations:    " . $invalidCount . " ❌\n";
    echo "Tolerance:               Rs " . $tolerance . "\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    if ($invalidCount === 0) {
        echo "✅ All refund calculations are correct!\n\n";
        exit(0);
    } else {
        echo "❌ Some refund calculations are incorrect.\n";
        echo "   Review the mismatches above and consider:\n";
        echo "   1. Running recalculate_refunds.php\n";
        echo "   2. Checking the discount handling logic\n";
        echo "   3. Verifying adjustment values\n\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
