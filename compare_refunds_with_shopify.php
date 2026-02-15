<?php
/**
 * Compare Database Refunds with Shopify API
 * 
 * Fetches refund data from Shopify and compares with what's in database
 * 
 * Usage: php compare_refunds_with_shopify.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use Illuminate\Support\Facades\Http;

$shopifyDomain = env('SHOPIFY_STORE_DOMAIN', '');
$accessToken = env('SHOPIFY_ACCESS_TOKEN', '');
$apiVersion = env('SHOPIFY_API_VERSION', '2026-01');

if (!$shopifyDomain || !$accessToken) {
    echo "❌ ERROR: Shopify configuration missing\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════\n";
echo "    COMPARE REFUNDS: DATABASE vs SHOPIFY\n";
echo "═══════════════════════════════════════════════════\n\n";

// Get orders with refunds
$orders = Order::whereHas('refunds')->take(3)->get();

if ($orders->count() === 0) {
    echo "No orders with refunds found.\n";
    exit(0);
}

$totalDbRefunds = 0;
$totalShopifyRefunds = 0;

foreach ($orders as $order) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ORDER #{$order->order_number}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Database total_refunds: Rs " . number_format($order->total_refunds, 2) . "\n\n";
    
    // Fetch from Shopify
    $url = "https://{$shopifyDomain}/admin/api/{$apiVersion}/orders/{$order->shopify_order_id}/refunds.json";
    
    try {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->timeout(30)->get($url);
        
        if (!$response->successful()) {
            echo "❌ API Error: " . $response->status() . "\n\n";
            continue;
        }
        
        $data = $response->json();
        $refunds = $data['refunds'] ?? [];
        
        echo "SHOPIFY DATA:\n";
        echo "Number of refunds: " . count($refunds) . "\n\n";
        
        $shopifyTotal = 0;
        
        foreach ($refunds as $index => $refund) {
            echo "Refund #" . ($index + 1) . ":\n";
            echo "  ID: {$refund['id']}\n";
            
            // Method 1: From transactions
            $transactionTotal = 0;
            foreach ($refund['transactions'] ?? [] as $trans) {
                if ($trans['kind'] === 'refund' && $trans['status'] === 'success') {
                    $transactionTotal += (float) $trans['amount'];
                }
            }
            echo "  From Transactions: Rs " . number_format($transactionTotal, 2) . "\n";
            
            // Method 2: From line items (subtotal only)
            $lineItemsSubtotal = 0;
            foreach ($refund['refund_line_items'] ?? [] as $item) {
                $lineItemsSubtotal += (float) ($item['subtotal'] ?? 0);
            }
            echo "  Line Items Subtotal: Rs " . number_format($lineItemsSubtotal, 2) . "\n";
            
            // Method 3: From line items (with tax)
            $lineItemsWithTax = 0;
            foreach ($refund['refund_line_items'] ?? [] as $item) {
                $lineItemsWithTax += (float) ($item['subtotal'] ?? 0);
                $lineItemsWithTax += (float) ($item['total_tax'] ?? 0);
            }
            echo "  Line Items + Tax: Rs " . number_format($lineItemsWithTax, 2) . "\n";
            
            // Order adjustments
            $adjustmentsAmount = 0;
            $adjustmentsWithTax = 0;
            foreach ($refund['order_adjustments'] ?? [] as $adj) {
                $adjustmentsAmount += (float) ($adj['amount'] ?? 0);
                $adjustmentsWithTax += $adjustmentsAmount + (float) ($adj['tax_amount'] ?? 0);
            }
            echo "  Adjustments Amount: Rs " . number_format($adjustmentsAmount, 2) . "\n";
            echo "  Adjustments + Tax: Rs " . number_format($adjustmentsWithTax, 2) . "\n";
            
            // Different calculation methods
            echo "\n  CALCULATION OPTIONS:\n";
            echo "    A) Transactions only:              Rs " . number_format($transactionTotal, 2) . "\n";
            echo "    B) Line Items Subtotal + Adj:      Rs " . number_format($lineItemsSubtotal + $adjustmentsAmount, 2) . "\n";
            echo "    C) Line Items+Tax + Adj+Tax:       Rs " . number_format($lineItemsWithTax + $adjustmentsWithTax, 2) . "\n";
            
            // Use transactions as the source of truth
            $shopifyTotal += $transactionTotal;
            echo "\n";
        }
        
        echo "SHOPIFY TOTAL (from transactions): Rs " . number_format($shopifyTotal, 2) . "\n";
        echo "DATABASE TOTAL:                     Rs " . number_format($order->total_refunds, 2) . "\n";
        echo "DIFFERENCE:                         Rs " . number_format($shopifyTotal - $order->total_refunds, 2) . "\n\n";
        
        $totalDbRefunds += $order->total_refunds;
        $totalShopifyRefunds += $shopifyTotal;
        
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    }
    
    usleep(500000); // Rate limit
}

echo "═══════════════════════════════════════════════════\n";
echo "    SUMMARY (Sample Orders)\n";
echo "═══════════════════════════════════════════════════\n";
echo sprintf("Database Total:     Rs %12.2f\n", $totalDbRefunds);
echo sprintf("Shopify Total:      Rs %12.2f\n", $totalShopifyRefunds);
echo sprintf("Difference:         Rs %12.2f\n", $totalShopifyRefunds - $totalDbRefunds);
echo "═══════════════════════════════════════════════════\n\n";

echo "💡 RECOMMENDATION:\n";
echo "Run the full comparison on all orders to see which method matches Shopify.\n";
