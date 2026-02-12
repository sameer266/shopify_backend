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

    public function syncOrders(Request $request)
    {
        if (!$this->shopifyDomain || !$this->accessToken) {
            return back()->with('error', 'Shopify configuration is missing');
        }

        try {
            if ($this->shouldPerformFullReset()) {
                $this->deleteAllExistingData();
            }

            $totalSynced = $this->fetchAllOrdersFromShopify();
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

    private function fetchAllOrdersFromShopify(): int
    {
        $baseUrl = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders.json";
        $lastOrderId = null;
        $totalSynced = 0;

        do {
            $params = ['limit' => 250, 'status' => 'any'];
            if ($lastOrderId) {
                $params['since_id'] = $lastOrderId;
            }

            $response = $this->makeShopifyRequest($baseUrl, $params);
            $orders = $response['orders'] ?? [];
            
            foreach ($orders as $orderData) {
                $orderData['refunds'] = $this->fetchOrderRefunds($orderData['id']);
                
                $this->saveOrder($orderData);
                $totalSynced++;
            }

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
            $response = $this->makeShopifyRequest($url);
            $refunds = $response['refunds'] ?? [];
            
            return $refunds;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch refunds for order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function makeShopifyRequest(string $url, array $params = []): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
        ])->timeout(30)->get($url, $params);

        if (!$response->successful()) {
            throw new \Exception('Shopify API request failed: ' . $response->body());
        }

        return $response->json();
    }

    private function shouldPerformFullReset(): bool
    {
        return filter_var(env('SHOPIFY_FULL_SYNC', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function deleteAllExistingData(): void
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

    public function saveOrder(array $orderData): void
    {
        $shopifyOrderId = (string) ($orderData['id'] ?? '');
        if (empty($shopifyOrderId)) {
            Log::warning('Skipping order with no ID');
            return;
        }

        if (empty($orderData['refunds'])) {
            $orderData['refunds'] = $this->fetchOrderRefunds($shopifyOrderId);
        }

        DB::transaction(function () use ($orderData, $shopifyOrderId) {
            $this->deleteExistingOrder($shopifyOrderId);
            $customerId = $this->saveCustomer($orderData['customer'] ?? null);
            $order = $this->createOrder($orderData, $customerId, $shopifyOrderId);
            $this->saveOrderItems($order, $orderData['line_items'] ?? []);
            $this->saveFulfillments($order, $orderData['fulfillments'] ?? []);
            $this->savePayments($order, $shopifyOrderId);
            $this->saveRefunds($order, $orderData['refunds'] ?? []);
            $this->updateOrderTotals($order, $orderData);
        });
    }

    private function deleteExistingOrder(string $shopifyOrderId): void
    {
        $existingOrder = Order::where('shopify_order_id', $shopifyOrderId)->first();

        if ($existingOrder) {
            $existingOrder->orderItems()->delete();
            $existingOrder->fulfillments()->delete();
            $existingOrder->payments()->delete();
            $existingOrder->refunds()->each(function ($refund) {
                $refund->orderAdjustments()->delete();
                $refund->refundItems()->delete();
                $refund->delete();
            });
            $existingOrder->delete();
        }
    }

    private function saveCustomer(?array $customerData): ?int
    {
        if (empty($customerData)) {
            return null;
        }

        $customer = Customer::updateOrCreate(
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

    private function createOrder(array $orderData, ?int $customerId, string $shopifyOrderId): Order
    {
        $subtotalPrice = (float) ($orderData['total_line_items_price'] ?? 0);
        $totalDiscounts = (float) ($orderData['total_discounts'] ?? 0);

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
            'total_discounts' => $totalDiscounts,
            'subtotal_price' => $subtotalPrice,
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

private function updateOrderTotals(Order $order, array $orderData): void
{
    $totalRefunds = $order->refunds()->sum('total_amount');

    // Sum all adjustments for the refunds of this order
    $totalAdjustments = $order->refunds()->with('orderAdjustments')
        ->get()
        ->sum(function($refund) {
            return $refund->orderAdjustments->sum('amount');
        });

    $order->update([
        'total_refunds' => $totalRefunds + $totalAdjustments,
    ]);
}

    private function saveOrderItems(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            $productId = !empty($item['product_id']) ? $this->saveProduct($item) : null;
            $taxAmount = collect($item['tax_lines'] ?? [])->sum(fn($tax) => (float)($tax['price'] ?? 0));
            $discountAmount = collect($item['discount_allocations'] ?? [])
                ->sum(fn($discount) => (float)($discount['amount'] ?? 0));

            $quantity = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $total = ($price * $quantity) - $discountAmount + $taxAmount;

            $order->orderItems()->create([
                'shopify_line_item_id' => (string) $item['id'],
                'product_id' => $productId,
                'title' => $item['title'] ?? 'Unknown Product',
                'sku' => $item['sku'] ?? null,
                'quantity' => $quantity,
                'price' => $price,
                'total' => $total,
                'discount_allocation' => $discountAmount,
                'properties' => $item['properties'] ?? [],
            ]);
        }
    }

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

    private function savePayments(Order $order, string $shopifyOrderId): void
    {
        $url = "https://{$this->shopifyDomain}/admin/api/{$this->apiVersion}/orders/{$shopifyOrderId}/transactions.json";

        try {
            $response = $this->makeShopifyRequest($url);
            $transactions = $response['transactions'] ?? [];

            foreach ($transactions as $transaction) {
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

    private function isCustomerPayment(array $transaction): bool
    {
        $validKinds = ['sale', 'capture', 'authorization'];
        $kind = $transaction['kind'] ?? '';
        $status = $transaction['status'] ?? '';

        return in_array($kind, $validKinds) && $status === 'success';
    }

    private function saveRefunds(Order $order, array $refunds): void
    {
        if (empty($refunds)) {
            return;
        }

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

            $this->saveRefundItems($refund, $order, $refundData['refund_line_items'] ?? []);
            $this->saveRefundAdjustments($refund, $refundData['order_adjustments'] ?? []);
            $this->updateRefundTotals($refund, $refundData);
        }
    }

    private function saveRefundItems($refund, Order $order, array $refundLineItems): void
    {
        foreach ($refundLineItems as $refundItem) {
            $lineItemId = (string) ($refundItem['line_item_id'] ?? '');
            $orderItem = $order->orderItems()
                ->where('shopify_line_item_id', $lineItemId)
                ->first();

            $subtotal = (float) ($refundItem['subtotal'] ?? 0);
            if ($subtotal === 0.0 && !empty($refundItem['subtotal_set']['shop_money']['amount'])) {
                $subtotal = (float) $refundItem['subtotal_set']['shop_money']['amount'];
            }

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
                    'subtotal' => $subtotal,
                    'total_tax' => $totalTax,
                    'restock_type' => $refundItem['restock_type'] ?? null,
                ]
            );
        }
    }

    private function updateRefundTotals($refund, array $refundData): void
    {
        $totalRefunded = 0;
        $gateway = null;

        foreach ($refundData['transactions'] ?? [] as $transaction) {
            if (($transaction['kind'] ?? '') === 'refund' && ($transaction['status'] ?? '') === 'success') {
                $totalRefunded += (float) ($transaction['amount'] ?? 0);

                if (!$gateway && !empty($transaction['gateway'])) {
                    $gateway = $transaction['gateway'];
                }
            }
        }

        $totalTax = collect($refundData['refund_line_items'] ?? [])
            ->sum(function ($item) {
                $tax = (float) ($item['total_tax'] ?? 0);
                if ($tax === 0.0 && !empty($item['total_tax_set']['shop_money']['amount'])) {
                    $tax = (float) $item['total_tax_set']['shop_money']['amount'];
                }
                return $tax;
            });

        $refund->update([
            'total_amount' => $totalRefunded,
            'total_tax' => $totalTax,
            'gateway' => $gateway ?? $refund->gateway,
        ]);
    }

    private function saveRefundAdjustments($refund, array $orderAdjustments): void
    {
        $refund->orderAdjustments()->delete();
        foreach ($orderAdjustments as $adj) {
            $amount = (float) ($adj['amount'] ?? 0);
            if (!empty($adj['amount_set']['shop_money']['amount'])) {
                $amount = (float) $adj['amount_set']['shop_money']['amount'];
            }
            $taxAmount = (float) ($adj['tax_amount'] ?? 0);
            if (!empty($adj['tax_amount_set']['shop_money']['amount'])) {
                $taxAmount = (float) $adj['tax_amount_set']['shop_money']['amount'];
            }
            $refund->orderAdjustments()->create([
                'shopify_adjustment_id' => (string) ($adj['id'] ?? ''),
                'kind' => $adj['kind'] ?? null,
                'reason' => $adj['reason'] ?? null,
                'amount' => $amount,
                'tax_amount' => $taxAmount,
            ]);
        }
    }
}
