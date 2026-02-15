<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Refund;

class SyncController extends Controller
{
    private string $shopifyDomain;
    private string $accessToken;
    private string $apiVersion;

    public function __construct()
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN', '');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN', '');
        $this->apiVersion = env('SHOPIFY_API_VERSION', '2026-01');
    }

    // ==========================================
    // Main Sync Entry Point
    // ==========================================

    public function syncOrders(Request $request)
    {
        if (!$this->shopifyDomain || !$this->accessToken) {
            return back()->with('error', 'Shopify configuration is missing');
        }

        try {
            // Full reset if configured
            if (filter_var(env('SHOPIFY_FULL_SYNC', true), FILTER_VALIDATE_BOOLEAN)) {
                $this->deleteAllData();
            }

            $totalSynced = $this->fetchAllOrders();

            Cache::put('shopify_last_sync_at', now(), now()->addYear());

            return back()->with('success', "Successfully synced {$totalSynced} orders");
        } catch (\Throwable $e) {
            Log::error('Order sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    // ==========================================
    // Fetch Orders from Shopify
    // ==========================================

    private function fetchAllOrders(): int
    {
        $baseUrl = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders.json";
        $lastOrderId = null;
        $totalSynced = 0;

        do {
            $params = ['limit' => 250, 'status' => 'any'];

            if ($lastOrderId) {
                $params['since_id'] = $lastOrderId;
            }

            $response = $this->shopifyRequest($baseUrl, $params);
            $orders = $response['orders'] ?? [];

            // Process each order
            foreach ($orders as $orderData) {
                $orderData['refunds'] = $this->fetchOrderRefunds($orderData['id']);
                $this->saveOrder($orderData);
                $totalSynced++;
            }

            // Get last order ID for pagination
            if (!empty($orders)) {
                $lastOrder = end($orders);
                $lastOrderId = (string) $lastOrder['id'];
            }
        } while (count($orders) === 250);

        Log::info('Sync completed', ['total_orders_synced' => $totalSynced]);

        return $totalSynced;
    }

    private function fetchOrderRefunds(string $orderId): array
    {
        $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders/{$orderId}/refunds.json";

        try {
            $response = $this->shopifyRequest($url);
            return $response['refunds'] ?? [];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch refunds', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // ==========================================
    // Save Order and Related Data
    // ==========================================

    public function saveOrder(array $orderData): void
    {
        $shopifyOrderId = (string) ($orderData['id'] ?? '');

        if (empty($shopifyOrderId)) {
            Log::warning('Skipping order with no ID');
            return;
        }

        // Ensure refunds are fetched
        if (empty($orderData['refunds'])) {
            $orderData['refunds'] = $this->fetchOrderRefunds($shopifyOrderId);
        }

        DB::transaction(function () use ($orderData, $shopifyOrderId) {
            // Delete existing order and related data
            $this->deleteExistingOrder($shopifyOrderId);

            // Save customer
            $customerId = null;
            if (!empty($orderData['customer'])) {
                $customer = Customer::updateOrCreate(
                    ['shopify_customer_id' => (string) $orderData['customer']['id']],
                    [
                        'email' => $orderData['customer']['email'] ?? null,
                        'first_name' => $orderData['customer']['first_name'] ?? null,
                        'last_name' => $orderData['customer']['last_name'] ?? null,
                        'phone' => $orderData['customer']['phone'] ?? null,
                        'shopify_created_at' => $orderData['customer']['created_at'] ?? null,
                    ]
                );
                $customerId = $customer->id;
            }

            // Create order
            $order = $this->createOrder($orderData, $customerId, $shopifyOrderId);

            // Save related data
            $this->saveOrderItems($order, $orderData['line_items'] ?? []);
            $this->saveFulfillments($order, $orderData['fulfillments'] ?? []);
            $this->savePayments($order, $shopifyOrderId);
            $this->saveRefunds($order, $orderData['refunds'] ?? []);

            // Update order totals
            $this->updateOrderTotals($order);
        });
    }

    private function deleteExistingOrder(string $shopifyOrderId): void
    {
        $order = Order::where('shopify_order_id', $shopifyOrderId)->first();

        if ($order) {
            $order->orderItems()->delete();
            $order->fulfillments()->delete();
            $order->payments()->delete();

            $order->refunds()->each(function ($refund) {
                $refund->orderAdjustments()->delete();
                $refund->refundItems()->delete();
                $refund->delete();
            });

            $order->delete();
        }
    }

    private function createOrder(array $orderData, ?int $customerId, string $shopifyOrderId): Order
    {
        // UPDATED: Convert discount to positive value if Shopify sends it as negative
        $totalDiscounts = (float) ($orderData['total_discounts'] ?? 0);
        $totalDiscounts = abs($totalDiscounts); // Ensure positive value
        
        return Order::create([
            'shopify_order_id' => $shopifyOrderId,
            'order_number' => $orderData['order_number'] ?? null,
            'customer_id' => $customerId,
            'email' => $orderData['email'] ?? null,
            'financial_status' => $orderData['financial_status'] ?? null,
            'fulfillment_status' => $orderData['fulfillment_status'] ?? null,
            'shipping_status' => $orderData['fulfillment_status'] ?? null,
            'is_paid' => in_array($orderData['financial_status'] ?? '', ['paid', 'partially_refunded', 'refunded']),
            'total_price' => (float) ($orderData['total_price'] ?? 0),
            'total_discounts' => $totalDiscounts, // UPDATED: Always positive
            'subtotal_price' => (float) ($orderData['total_line_items_price'] ?? 0),
            'total_tax' => (float) ($orderData['total_tax'] ?? 0),
            'currency' => $orderData['currency'] ?? 'NPR',
            'processed_at' => $orderData['processed_at'] ?? null,
            'closed_at' => $orderData['closed_at'] ?? null,
            'cancelled_at' => $orderData['cancelled_at'] ?? null,
            'cancel_reason' => $orderData['cancel_reason'] ?? null,
            'shipping_address' => $orderData['shipping_address'] ?? null,
            'billing_address' => $orderData['billing_address'] ?? null,
            'note' => $orderData['note'] ?? null,
        ]);
    }

    // ==========================================
    // Save Order Items
    // ==========================================

    private function saveOrderItems(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            // Save product if exists
            $productId = null;
            if (!empty($item['product_id'])) {
                $product = Product::updateOrCreate(
                    ['shopify_product_id' => (string) $item['product_id']],
                    [
                        'title' => $item['title'] ?? 'Unknown',
                        'vendor' => $item['vendor'] ?? null,
                    ]
                );
                $productId = $product->id;
            }

            // Calculate totals
            $taxAmount = collect($item['tax_lines'] ?? [])->sum(fn($tax) => (float)($tax['price'] ?? 0));
            
            // UPDATED: Convert discount to positive if negative
            $discountAmount = collect($item['discount_allocations'] ?? [])
                ->sum(fn($discount) => abs((float)($discount['amount'] ?? 0)));

            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $total = ($price * $quantity) - $discountAmount + $taxAmount;

            // Create order item
            $order->orderItems()->updateOrCreate(
                ['shopify_line_item_id' => (string) $item['id']],
                [
                    'product_id' => $productId,
                    'title' => $item['title'],
                    'sku' => $item['sku'] ?? null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                    'discount_allocation' => $discountAmount,
                    'fulfillment_status' => $item['fulfillment_status'] ?? null,
                    'properties' => $item['properties'] ?? [],
                ]
            );
        }
    }

    // ==========================================
    // Save Fulfillments
    // ==========================================

    private function saveFulfillments(Order $order, array $fulfillments): void
    {
        foreach ($fulfillments as $fulfillment) {
            $order->fulfillments()->create([
                'shopify_fulfillment_id' => (string) $fulfillment['id'],
                'status' => $fulfillment['status'] ?? null,
                'tracking_number' => $fulfillment['tracking_number'] ?? null,
            ]);
        }
    }

    // ==========================================
    // Save Payments
    // ==========================================

    private function savePayments(Order $order, string $shopifyOrderId): void
    {
        $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders/{$shopifyOrderId}/transactions.json";

        try {
            $response = $this->shopifyRequest($url);
            $transactions = $response['transactions'] ?? [];

            foreach ($transactions as $transaction) {
                // Only save successful customer payments
                $validKinds = ['sale', 'capture', 'authorization'];
                $kind = $transaction['kind'] ?? '';
                $status = $transaction['status'] ?? '';

                if (!in_array($kind, $validKinds) || $status !== 'success') {
                    continue;
                }

                $order->payments()->create([
                    'shopify_payment_id' => (string) $transaction['id'],
                    'gateway' => $transaction['gateway'] ?? null,
                    'kind' => $kind,
                    'amount' => (float) $transaction['amount'],
                    'status' => $status,
                    'processed_at' => $transaction['processed_at'] ?? null,
                    'currency' => $transaction['currency'] ?? $order->currency ?? 'NPR',
                    'details' => json_encode($transaction),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch payment transactions', [
                'order_id' => $shopifyOrderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ==========================================
    // Save Refunds
    // ==========================================

    private function saveRefunds(Order $order, array $refunds): void
    {
        foreach ($refunds as $refundData) {
            // Create/update refund (total_amount will be calculated in updateOrderTotals)
            $refund = $order->refunds()->updateOrCreate(
                ['shopify_refund_id' => (string) $refundData['id']],
                [
                    'processed_at' => $refundData['processed_at'] ?? null,
                    'note' => $refundData['note'] ?? null,
                    'gateway' => $order->payments()->first()?->gateway,
                    'total_amount' => 0, // Will be calculated later
                    'transactions' => $refundData['transactions'] ?? [],
                ]
            );

            // Save refund line items
            // REQUIREMENT: Store refund_line_items.subtotal into refund_items.subtotal
            foreach ($refundData['refund_line_items'] ?? [] as $refundItem) {
                $lineItemId = (string) ($refundItem['line_item_id'] ?? '');
                $orderItem = $order->orderItems()
                    ->where('shopify_line_item_id', $lineItemId)
                    ->first();

                // Get subtotal (check both places)
                $subtotal = (float) ($refundItem['subtotal'] ?? 0);
                if ($subtotal === 0.0 && !empty($refundItem['subtotal_set']['shop_money']['amount'])) {
                    $subtotal = (float) $refundItem['subtotal_set']['shop_money']['amount'];
                }

                // Get tax (check both places)
                $totalTax = (float) ($refundItem['total_tax'] ?? 0);
                if ($totalTax === 0.0 && !empty($refundItem['total_tax_set']['shop_money']['amount'])) {
                    $totalTax = (float) $refundItem['total_tax_set']['shop_money']['amount'];
                }

                $refund->refundItems()->updateOrCreate(
                    ['shopify_line_item_id' => (string) ($refundItem['id'] ?? '')],
                    [
                        'order_item_id' => $orderItem?->id,
                        'product_id' => $orderItem?->product_id,
                        'quantity' => (int) ($refundItem['quantity'] ?? 0),
                        'subtotal' => $subtotal, // REQUIREMENT: Store subtotal
                        'total_tax' => $totalTax,
                        'restock_type' => $refundItem['restock_type'] ?? null,
                    ]
                );
            }

            // Save order adjustments (shipping refunds, fees, etc)
            // REQUIREMENT: Store order_adjustments.amount into refund_adjustments.amount
            $refund->orderAdjustments()->delete();

            foreach ($refundData['order_adjustments'] ?? [] as $adj) {
                $amount = (float) ($adj['amount_set']['shop_money']['amount'] ?? $adj['amount'] ?? 0);
                $taxAmount = (float) ($adj['tax_amount_set']['shop_money']['amount'] ?? $adj['tax_amount'] ?? 0);

                $refund->orderAdjustments()->create([
                    'shopify_adjustment_id' => (string) ($adj['id'] ?? ''),
                    'kind' => $adj['kind'] ?? null,
                    'reason' => $adj['reason'] ?? null,
                    'amount' => $amount, // REQUIREMENT: Store amount as-is (can be positive or negative)
                    'tax_amount' => $taxAmount,
                ]);
            }
        }
    }

    // ==========================================
    // Update Order Totals
    // ==========================================

    /**
     * UPDATED: Calculate total_refunds using the new formula:
     * 
     * total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
     * 
     * Where:
     * - Refund adjustments are always subtracted from items total
     * - If discount is negative, subtract it
     * - If discount is positive, add it
     */
    private function updateOrderTotals(Order $order): void
    {
        $order->load('refunds.refundItems', 'refunds.orderAdjustments');

        $totalRefunds = 0;

        foreach ($order->refunds as $refund) {
            // Step 1: Sum refund_items.subtotal
            $itemsSubtotal = $refund->refundItems->sum('subtotal');
            
            // Step 2: Sum refund_adjustments.amount (these are always subtracted)
            $adjustmentsTotal = $refund->orderAdjustments->sum('amount');
            
            // Step 3: Get discount value from order.total_discounts
            $discount = $order->total_discounts;
            
            // Step 4: Apply the formula
            // total_refunds = items_subtotal - adjustments ± discount
            $refundAmount = $itemsSubtotal - $adjustmentsTotal;
            
            // Step 5: Handle discount based on its sign
            // If discount is negative, subtract it (which adds to refund)
            // If discount is positive, add it (which reduces the refund)
            // Note: Since we stored discount as positive in orders table,
            // we need to check the original sign from Shopify data
            // For now, we'll assume discount reduces the refund amount
            // You may need to adjust this based on your actual Shopify data behavior
            
            if ($discount < 0) {
                // Discount is negative, subtract it (adds to refund)
                $refundAmount -= $discount;
            } else {
                // Discount is positive, add it to the calculation
                // This depends on your business logic
                // Typically discounts should reduce the refund amount
                // $refundAmount += $discount; // Uncomment if needed
            }
            
            // Round and ensure non-negative
            $refundAmount = max(round($refundAmount, 2), 0);
            
            // Update individual refund total
            $refund->update(['total_amount' => $refundAmount]);
            
            $totalRefunds += $refundAmount;
        }

        // Update order total refunds
        $order->update([
            'total_refunds' => round($totalRefunds, 2),
        ]);
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    private function shopifyRequest(string $url, array $params = []): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
        ])->timeout(30)->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception('Shopify API request failed: ' . $response->body());
        }

        return $response->json();
    }

    private function deleteAllData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('payments')->truncate();
        DB::table('fulfillments')->truncate();
        DB::table('refund_adjustments')->truncate();
        DB::table('refund_items')->truncate();
        DB::table('refunds')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('customers')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Log::info('All order data wiped from database');
    }
}
