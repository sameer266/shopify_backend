<?php
/**
 * Debug Refunds - Detailed Analysis
 * 
 * This script shows exactly what's in your refund tables
 * 
 * Usage: php debug_refund_details.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════\n";
echo "    REFUND DATA ANALYSIS\n";
echo "═══════════════════════════════════════════════════\n\n";

// Get all refunds with their items and adjustments
$refunds = Refund::with(['order', 'refundItems', 'orderAdjustments'])->get();

$totalCalculated = 0;
$totalStored = 0;

foreach ($refunds as $refund) {
    $orderNumber = $refund->order->order_number ?? $refund->order->shopify_order_id ?? 'N/A';
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "REFUND #{$refund->id} - Order #{$orderNumber}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Shopify Refund ID: {$refund->shopify_refund_id}\n";
    echo "Stored total_amount: Rs " . number_format($refund->total_amount, 2) . "\n\n";
    
    // Refund Items
    echo "REFUND ITEMS:\n";
    $itemsSubtotal = 0;
    $itemsTax = 0;
    
    if ($refund->refundItems->count() > 0) {
        foreach ($refund->refundItems as $item) {
            $itemsSubtotal += (float) $item->subtotal;
            $itemsTax += (float) $item->total_tax;
            
            echo sprintf(
                "  - Qty: %2d | Subtotal: Rs %8.2f | Tax: Rs %8.2f | Total: Rs %8.2f\n",
                $item->quantity,
                $item->subtotal,
                $item->total_tax,
                $item->subtotal + $item->total_tax
            );
        }
        echo sprintf("  ITEMS SUBTOTAL: Rs %8.2f\n", $itemsSubtotal);
        echo sprintf("  ITEMS TAX:      Rs %8.2f\n", $itemsTax);
        echo sprintf("  ITEMS TOTAL:    Rs %8.2f\n\n", $itemsSubtotal + $itemsTax);
    } else {
        echo "  (No refund items)\n\n";
    }
    
    // Order Adjustments
    echo "ORDER ADJUSTMENTS:\n";
    $adjustmentsAmount = 0;
    $adjustmentsTax = 0;
    
    if ($refund->orderAdjustments->count() > 0) {
        foreach ($refund->orderAdjustments as $adj) {
            $adjustmentsAmount += (float) $adj->amount;
            $adjustmentsTax += (float) $adj->tax_amount;
            
            echo sprintf(
                "  - Kind: %-20s | Amount: Rs %8.2f | Tax: Rs %8.2f | Total: Rs %8.2f\n",
                $adj->kind ?? 'N/A',
                $adj->amount,
                $adj->tax_amount,
                $adj->amount + $adj->tax_amount
            );
        }
        echo sprintf("  ADJ AMOUNT:     Rs %8.2f\n", $adjustmentsAmount);
        echo sprintf("  ADJ TAX:        Rs %8.2f\n", $adjustmentsTax);
        echo sprintf("  ADJ TOTAL:      Rs %8.2f\n\n", $adjustmentsAmount + $adjustmentsTax);
    } else {
        echo "  (No adjustments)\n\n";
    }
    
    // Calculations
    $calcWithoutTax = $itemsSubtotal + $adjustmentsAmount;
    $calcWithTax = ($itemsSubtotal + $itemsTax) + ($adjustmentsAmount + $adjustmentsTax);
    
    echo "CALCULATIONS:\n";
    echo sprintf("  Items Subtotal + Adj Amount (no tax):  Rs %8.2f\n", $calcWithoutTax);
    echo sprintf("  Items Total + Adj Total (with tax):    Rs %8.2f\n", $calcWithTax);
    echo sprintf("  Stored in DB (total_amount):           Rs %8.2f\n", $refund->total_amount);
    
    $totalCalculated += $calcWithoutTax;
    $totalStored += $refund->total_amount;
    
    echo "\n";
}

echo "═══════════════════════════════════════════════════\n";
echo "    GRAND TOTALS\n";
echo "═══════════════════════════════════════════════════\n";
echo sprintf("Total Calculated (no tax):   Rs %12.2f\n", $totalCalculated);
echo sprintf("Total Stored in DB:          Rs %12.2f\n", $totalStored);
echo sprintf("Shopify Expected:            Rs %12.2f\n", 23731.00);
echo "═══════════════════════════════════════════════════\n\n";

// Check for duplicates
echo "CHECKING FOR DUPLICATE REFUNDS...\n\n";
$duplicates = Refund::select('shopify_refund_id', DB::raw('COUNT(*) as count'))
    ->groupBy('shopify_refund_id')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "⚠️  DUPLICATES FOUND:\n";
    foreach ($duplicates as $dup) {
        echo "  - Shopify Refund ID {$dup->shopify_refund_id}: {$dup->count} records\n";
        
        // Show which records
        $records = Refund::where('shopify_refund_id', $dup->shopify_refund_id)->get();
        foreach ($records as $rec) {
            echo "    → DB ID: {$rec->id}, Order: #{$rec->order->order_number}\n";
        }
    }
    echo "\n";
} else {
    echo "✓ No duplicate refunds found\n\n";
}

// Summary by order
echo "REFUNDS BY ORDER:\n\n";
$orderRefunds = Order::whereHas('refunds')
    ->with('refunds')
    ->get()
    ->map(function($order) {
        return [
            'order_number' => $order->order_number ?? $order->shopify_order_id,
            'refund_count' => $order->refunds->count(),
            'total_refunds' => $order->total_refunds,
            'calculated' => $order->refunds->sum('total_amount'),
        ];
    });

foreach ($orderRefunds as $or) {
    echo sprintf(
        "Order #%-10s | Refunds: %2d | Stored: Rs %10.2f | Sum: Rs %10.2f | Match: %s\n",
        $or['order_number'],
        $or['refund_count'],
        $or['total_refunds'],
        $or['calculated'],
        abs($or['total_refunds'] - $or['calculated']) < 0.01 ? '✓' : '✗'
    );
}

echo "\n";
