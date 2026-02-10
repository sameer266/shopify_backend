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
    /**
     * MANUAL FULL SYNC
     * Deletes all old data → inserts fresh Shopify data
     */
    public function syncOrders(Request $request)
    {
        $domain = env('SHOPIFY_STORE_DOMAIN');
        $token  = env('SHOPIFY_ACCESS_TOKEN');
        $apiVer = env('SHOPIFY_API_VERSION', '2026-01');


        if (!$domain || !$token) {
            return back()->with('error', 'Shopify config missing');
        }

        try {

            /**  FULL RESET (ONLY FOR MANUAL SYNC) */
            if (filter_var(env('SHOPIFY_FULL_SYNC', true), FILTER_VALIDATE_BOOLEAN)) {
                $this->wipeAllOrders();
            }


            $url     = "https://{$domain}/admin/api/{$apiVer}/orders.json";
            $sinceId = null;
            $synced  = 0;

            do {
                $query = [
                   'limit'=>250,
                   
                ];

                if ($sinceId) {
                    $query['since_id'] = $sinceId;
                }

                $res = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                ])->get($url, $query);

                if (!$res->successful()) {
                    throw new \Exception('Shopify API failed');
                }

                $orders = $res->json('orders', []);

                foreach ($orders as $orderData) {
                    $this->replaceOrder($orderData);
                    $synced++;
                }

                if (!empty($orders)) {
                    $sinceId = (string) end($orders)['id'];
                }
            } while (count($orders) === 250);

            Cache::put('shopify_last_sync_at', now(), now()->addYear());

            return back()->with('success', "Synced {$synced} orders");
        } catch (\Throwable $e) {
            Log::error('Order sync failed', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * FULL DATABASE WIPE (FK SAFE)
     */
    private function wipeAllOrders(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('payments')->truncate();
        DB::table('fulfillments')->truncate();
        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('customers')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * DELETE OLD ORDER → INSERT NEW VERSION
     * Used by BOTH sync + webhook
     */
    public function replaceOrder(array $p): void
    {
        $shopifyOrderId = (string) ($p['id'] ?? '');
        if ($shopifyOrderId === '') return;

        DB::transaction(function () use ($p, $shopifyOrderId) {

            /**  Delete old order completely */
            if ($old = Order::where('shopify_order_id', $shopifyOrderId)->first()) {
                $old->orderItems()->delete();
                $old->fulfillments()->delete();
                $old->payments()->delete();
                $old->delete();
            }

            /**  Customer */
            $customerId = null;
            if (!empty($p['customer'])) {
                $c = $p['customer'];

                $customer = Customer::firstOrCreate(
                    ['shopify_customer_id' => (string) $c['id']],
                    [
                        'email'      => $c['email'] ?? null,
                        'first_name' => $c['first_name'] ?? null,
                        'last_name'  => $c['last_name'] ?? null,
                        'phone'      => $c['phone'] ?? null,
                    ]
                );

                $customerId = $customer->id;
            }

            /**  Order */
            $order = Order::create([
                'shopify_order_id'   => $shopifyOrderId,
                'order_number'       => $p['order_number'] ?? null,
                'customer_id'        => $customerId,
                'email'              => $p['email'] ?? null,
                'financial_status'   => $p['financial_status'] ?? null,
                'fulfillment_status' => $p['fulfillment_status'] ?? null,
                'shipping_status'    => $p['fulfillment_status'] ?? null,
                'is_paid'            => ($p['financial_status'] ?? '') === 'paid',
                'total_price'        => (float) ($p['total_price'] ?? 0),
                'subtotal_price'     => (float) ($p['subtotal_price'] ?? 0),
                'total_tax'          => (float) ($p['total_tax'] ?? 0),
                'currency'           => $p['currency'] ?? 'NPR',
                'processed_at'       => $p['processed_at'] ?? null,
                'closed_at'          => $p['closed_at'] ?? null,
                'cancelled_at'       => $p['cancelled_at'] ?? null,
                'cancel_reason'      => $p['cancel_reason'] ?? null,
                'shipping_address'   => $p['shipping_address'] ?? null,
                'billing_address'    => $p['billing_address'] ?? null,
                'note'               => $p['note'] ?? null,
            ]);


            /**  Line Items + Products */
            foreach ($p['line_items'] ?? [] as $item) {

                $productId = null;

                if (!empty($item['product_id'])) {

                    // Prepare data for firstOrCreate/update
                    $productData = [
                        'title'        => $item['title'] ?? 'Unknown',
    
                        'vendor'       => $item['vendor'] ?? null,

                     
                    ];

                    // Save product (firstOrCreate on Shopify product ID)
                    $product = Product::updateOrCreate(
                        ['shopify_product_id' => (string) $item['product_id']],
                        $productData
                    );

                    $productId = $product->id;
                }

                // Create order item
                $order->orderItems()->create([
                    'shopify_line_item_id' => (string) $item['id'],
                    'product_id'           => $productId,
                    'title'                => $item['title'] ?? '',
                    'sku'                  => $item['sku'] ?? null,
                    'quantity'             => (int) $item['quantity'],
                    'price'                => (float) $item['price'],
                    'total'                => (float) $item['price'] * (int) $item['quantity'],
                ]);
            }


            /** Fulfillments */
            foreach ($p['fulfillments'] ?? [] as $f) {
                $order->fulfillments()->create([
                    'shopify_fulfillment_id' => (string) $f['id'],
                    'status'                 => $f['status'] ?? null,
                    'tracking_number'        => $f['tracking_number'] ?? null,
                ]);
            }

            /**  Customer Payments (NO REFUNDS) */
            $this->syncCustomerPayments($order, $shopifyOrderId);
        });
    }


    private function syncCustomerPayments(Order $order, string $shopifyOrderId): void
    {
        $domain = env('SHOPIFY_STORE_DOMAIN');
        $token  = env('SHOPIFY_ACCESS_TOKEN');

        $url = "https://{$domain}/admin/api/2026-01/orders/{$shopifyOrderId}/transactions.json";

        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
        ])->get($url);

        if (!$res->successful()) return;

        $transactions = $res->json()['transactions'] ?? [];

        foreach ($transactions as $txn) {

            // ONLY customer payments
            if (!in_array($txn['kind'], ['sale', 'capture', 'authorization'])) {
                continue;
            }

            if (($txn['status'] ?? '') !== 'success') {
                continue;
            }

            $order->payments()->create([
                'shopify_payment_id' => (string) $txn['id'],
                'gateway'            => $txn['gateway'] ?? null,
                'kind'               => $txn['kind'],
                'amount'             => (float) $txn['amount'],
                'status'             => $txn['status'],
                'processed_at'       => $txn['processed_at'] ?? null,
                'currency'           => $txn['currency'] ?? $order->currency ?? 'NPR',
                'details'            => json_encode($txn),
            ]);
        }
    }
}
