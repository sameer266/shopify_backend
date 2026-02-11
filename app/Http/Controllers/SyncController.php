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

    // =======================
    // Main Sync Entry Point
    // =======================
    
    /**
     * Sync all orders from Shopify to local database
     */
    public function syncOrders(Request $request)
    {
        // Make sure we have Shopify credentials
        if (!$this->shopifyDomain || !$this->accessToken) {
            return back()->with('error', 'Shopify configuration is missing');
        }

        try {
            // Wipe existing data if needed (based on .env setting)
            if ($this->shouldPerformFullReset()) {
                $this->deleteAllExistingData();
            }

            // Fetch all orders from Shopify
            $totalSynced = $this->fetchAllOrdersFromShopify();

            // Remember when we last synced
            Cache::put('shopify_last_sync_at', now(), now()->addYear());

            return back()->with('success', "Successfully synced {$totalSynced} orders");

        } catch (\Throwable $exception) {
            Log::error('Order sync failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            return back()->with('error', 'Sync failed: ' . $exception->getMessage());
        }
    }

    // =============================
    // Shopify API Communication
    // =============================
    
    /**
     * Fetch all orders from Shopify using pagination
     */
    private function fetchAllOrdersFromShopify(): int
    {
        $baseUrl = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders.json";
        $lastOrderId = null;
        $totalSynced = 0;

        do {
            // Build query parameters
            $params = [
                'limit' => 250,
                'status' => 'any', // Get all orders regardless of status
            
             
            ];

            // For pagination, get orders after the last one we saw
            if ($lastOrderId) {
                $params['since_id'] = $lastOrderId;
            }

            // Fetch batch of orders from Shopify
            $response = $this->makeShopifyRequest($baseUrl, $params);
            $orders = $response['orders'] ?? [];
            
            Log::info('Fetched orders from Shopify', ['count' => count($orders)]);

            // Save each order to our database
            foreach ($orders as $orderData) {
                $this->saveOrder($orderData);
                $totalSynced++;
            }

            // Get the last order ID for next pagination
            if (!empty($orders)) {
                $lastOrder = end($orders);
                $lastOrderId = (string) $lastOrder['id'];
            }

        } while (count($orders) === 250); // Keep going if we got a full page

        return $totalSynced;
    }

    /**
     * Make a request to Shopify API
     */
    private function makeShopifyRequest(string $url, array $params = []): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
        ])->get($url, $params);
        

        if (!$response->successful()) {
            throw new \Exception('Shopify API request failed: ' . $response->body());
        }

        return $response->json();
    }

    // ========================
    // Database Management
    // ========================
    
    /**
     * Check if we should wipe all data before syncing
     */
    private function shouldPerformFullReset(): bool
    {
        return filter_var(env('SHOPIFY_FULL_SYNC', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Delete all existing order data from database
     */
    private function deleteAllExistingData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('payments')->truncate();
        DB::table('fulfillments')->truncate();
        DB::table('refund_items')->truncate();
        DB::table('refunds')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('customers')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Log::info('All order data wiped from database');
    }

    // ===========================
    // Save Order & Related Data
    // ===========================
    
    /**
     * Save or update an order in the database
     */
    public function saveOrder(array $orderData): void
    {
        $shopifyOrderId = (string) ($orderData['id'] ?? '');
        
        if (empty($shopifyOrderId)) {
            Log::warning('Skipping order with no ID');
            return;
        }

        DB::transaction(function () use ($orderData, $shopifyOrderId) {
            // Remove old version if it exists
            $this->deleteExistingOrder($shopifyOrderId);

            // Save customer first
            $customerId = $this->saveCustomer($orderData['customer'] ?? null);

            // Create the order
            $order = $this->createOrder($orderData, $customerId, $shopifyOrderId);

            // Add all the related data
            $this->saveOrderItems($order, $orderData['line_items'] ?? []);
            $this->saveFulfillments($order, $orderData['fulfillments'] ?? []);
            $this->savePayments($order, $shopifyOrderId);
            $this->saveRefunds($order, $orderData['refunds'] ?? []);

            // Update totals now that we have all the data
            $this->updateOrderTotals($order, $orderData);
        });
    }

    /**
     * Delete existing order and all related data
     */
    private function deleteExistingOrder(string $shopifyOrderId): void
    {
        $existingOrder = Order::where('shopify_order_id', $shopifyOrderId)->first();

        if ($existingOrder) {
            $existingOrder->orderItems()->delete();
            $existingOrder->fulfillments()->delete();
            $existingOrder->payments()->delete();
            $existingOrder->refunds()->each(function ($refund) {
                $refund->refundItems()->delete();
                $refund->delete();
            });
            $existingOrder->delete();
        }
    }

    // ==================
    // Save Customers
    // ==================
    
    /**
     * Create or update customer record
     */
    private function saveCustomer(?array $customerData): ?int
    {
        if (empty($customerData)) {
            return null;
        }

        $customer = Customer::firstOrCreate(
            ['shopify_customer_id' => (string) $customerData['id']],
            [
                'email' => $customerData['email'] ?? null,
                'first_name' => $customerData['first_name'] ?? null,
                'last_name' => $customerData['last_name'] ?? null,
                'phone' => $customerData['phone'] ?? null,
            ]
        );

        return $customer->id;
    }

    // ===============
    // Save Orders
    // ===============
    
    /**
     * Create the main order record
     */
    private function createOrder(array $orderData, ?int $customerId, string $shopifyOrderId): Order
    {
        return Order::create([
            'shopify_order_id' => $shopifyOrderId,
            'order_number' => $orderData['order_number'] ?? null,
            'customer_id' => $customerId,
            'email' => $orderData['email'] ?? null,
            'financial_status' => $orderData['financial_status'] ?? null,
            'fulfillment_status' => $orderData['fulfillment_status'] ?? null,
            'shipping_status' => $orderData['fulfillment_status'] ?? null,
            'is_paid' => ($orderData['financial_status'] ?? '') === 'paid',
            'total_price' => (float) ($orderData['total_price'] ?? 0),
            'total_discounts' => (float) ($orderData['total_discounts'] ?? 0),

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

    /**
     * Update order totals after everything is saved
     */
    private function updateOrderTotals(Order $order, array $orderData): void
    {
        $totalRefunds = $order->refunds()->sum('total_amount');
      

        $order->update([
            'total_refunds' => $totalRefunds,
          
        ]);
    }

    // ====================
    // Save Order Items
    // ====================
    
    /**
     * Save order line items and their products
     */
    private function saveOrderItems(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            $productId = null;

            // Save product if we have a product ID
            if (!empty($item['product_id'])) {
                $productId = $this->saveProduct($item);
            }

            // Calculate tax for this line item
            $taxAmount = collect($item['tax_lines'] ?? [])
                ->sum(fn($tax) => (float)($tax['price'] ?? 0));

            // Calculate discount for this line item
            $discountAmount = collect($item['discount_allocations'] ?? [])
                ->sum(fn($discount) => (float)($discount['amount'] ?? 0));

            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);

            // Total = (Price * Quantity) - Discount + Tax
            $total = ($price * $quantity) - $discountAmount + $taxAmount;

            $order->orderItems()->create([
                'shopify_line_item_id' => (string) $item['id'],
                'product_id' => $productId,
                'title' => $item['title'] ?? 'Unknown Product',
                'sku' => $item['sku'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'properties' => $item['properties'] ?? [], // Pass properties array directly, cast handles it
            ]);
        }
    }

    // =================
    // Save Products
    // =================
    
    /**
     * Create or update a product
     */
    private function saveProduct(array $itemData): int
    {
        $product = Product::updateOrCreate(
            ['shopify_product_id' => (string) $itemData['product_id']],
            [
                'title' => $itemData['title'] ?? 'Unknown',
                'vendor' => $itemData['vendor'] ?? null,
            ]
        );

        return $product->id;
    }

    // =====================
    // Save Fulfillments
    // =====================
    
    /**
     * Save order fulfillments (shipping info)
     */
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

    // =================
    // Save Payments
    // =================
    
    /**
     * Fetch and save payment transactions for an order
     */
    private function savePayments(Order $order, string $shopifyOrderId): void
    {
        $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders/{$shopifyOrderId}/transactions.json";

        try {
            $response = $this->makeShopifyRequest($url);
            $transactions = $response['transactions'] ?? [];

            foreach ($transactions as $transaction) {
                // Only save successful customer payments
                if (!$this->isCustomerPayment($transaction)) {
                    continue;
                }

                $order->payments()->create([
                    'shopify_payment_id' => (string) $transaction['id'],
                    'gateway' => $transaction['gateway'] ?? null,
                    'kind' => $transaction['kind'],
                    'amount' => (float) $transaction['amount'],
                    'status' => $transaction['status'],
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

    /**
     * Check if transaction is a customer payment (not a refund)
     */
    private function isCustomerPayment(array $transaction): bool
    {
        $validKinds = ['sale', 'capture', 'authorization'];
        $kind = $transaction['kind'] ?? '';
        $status = $transaction['status'] ?? '';

        return in_array($kind, $validKinds) && $status === 'success';
    }

    // ================
    // Save Refunds
    // ================
    
    /**
     * Save refunds and refund line items
     */
    private function saveRefunds(Order $order, array $refunds): void
    {
        foreach ($refunds as $refundData) {
            $refund = $order->refunds()->updateOrCreate(
                ['shopify_refund_id' => (string) $refundData['id']],
                [
                    'processed_at' => $refundData['processed_at'] ?? null,
                    'note' => $refundData['note'] ?? null,
                    'gateway' => $order->payments()->first()?->gateway,
                    'transactions' => $refundData['transactions'] ?? [],
                ]
            );

            // Save individual refund items
            $this->saveRefundItems($refund, $order, $refundData['refund_line_items'] ?? []);

            // Calculate totals from transactions
            $this->updateRefundTotals($refund, $refundData);
        }
    }

    /**
     * Save individual refund line items
     */
    private function saveRefundItems($refund, Order $order, array $refundLineItems): void
    {
        foreach ($refundLineItems as $refundItem) {
            // Find the original order item
            $lineItemId = (string) ($refundItem['line_item_id'] ?? '');
            $orderItem = $order->orderItems()
                ->where('shopify_line_item_id', $lineItemId)
                ->first();

            $refund->refundItems()->updateOrCreate(
                ['shopify_line_item_id' => (string) ($refundItem['id'] ?? '')],
                [
                    'order_item_id' => $orderItem?->id,
                    'product_id' => $orderItem?->product_id,
                    'quantity' => (int) ($refundItem['quantity'] ?? 0),
                    'subtotal' => (float) ($refundItem['subtotal'] ?? 0),
                    'total_tax' => (float) ($refundItem['total_tax'] ?? 0),
                    'restock_type' => $refundItem['restock_type'] ?? null,
                ]
            );
        }
    }

    /**
     * Calculate and update refund totals from transaction data
     */
    private function updateRefundTotals($refund, array $refundData): void
    {
        $totalRefunded = 0;
        $gateway = null;

        // Sum up all successful refund transactions
        foreach ($refundData['transactions'] ?? [] as $transaction) {
            if (($transaction['kind'] ?? '') === 'refund' && ($transaction['status'] ?? '') === 'success') {
                $totalRefunded += (float) ($transaction['amount'] ?? 0);

                if (!$gateway && !empty($transaction['gateway'])) {
                    $gateway = $transaction['gateway'];
                }
            }
        }

        // Calculate total tax from refund items
        $totalTax = collect($refundData['refund_line_items'] ?? [])
            ->sum(fn($item) => (float) ($item['total_tax'] ?? 0));

        $refund->update([
            'total_amount' => $totalRefunded,
            'total_tax' => $totalTax,
            'gateway' => $gateway ?? $refund->gateway,
        ]);
    }
}