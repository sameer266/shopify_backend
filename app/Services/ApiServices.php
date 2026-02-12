<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiServices
{
    private string $domain;
    private string $token;
    private string $version;

    public function __construct()
    {
        $this->domain = env('SHOPIFY_STORE_DOMAIN');
        $this->token = env('SHOPIFY_ACCESS_TOKEN');
        $this->version = env('SHOPIFY_API_VERSION', '2026-01');
    }

    /**
     * Generic REST GET Request
     */
    public function get(string $endpoint, array $params = [])
    {
        $url = "https://{$this->domain}/admin/api/{$this->version}/{$endpoint}";
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->get($url, $params);

        if ($response->failed()) {
            Log::error("Shopify REST GET Error: {$endpoint}", ['body' => $response->body()]);
            throw new \Exception("Shopify API Error: " . $response->json()['errors'] ?? $response->body());
        }

        return $response->json();
    }

    /**
     * Generic REST POST Request
     */
    public function post(string $endpoint, array $data = [])
    {
        $url = "https://{$this->domain}/admin/api/{$this->version}/{$endpoint}";
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        if ($response->failed()) {
            Log::error("Shopify REST POST Error: {$endpoint}", ['body' => $response->body()]);
            throw new \Exception("Shopify API Error: " . json_encode($response->json()));
        }

        return $response->json();
    }

    /**
     * Generic GraphQL Request
     */
    public function graphQL(string $query, array $variables = [])
    {
        $url = "https://{$this->domain}/admin/api/{$this->version}/graphql.json";
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($response->failed()) {
            Log::error("Shopify GraphQL Error", ['body' => $response->body()]);
            throw new \Exception("Shopify GraphQL Error: " . $response->body());
        }

        $body = $response->json();

        if (isset($body['errors'])) {
            Log::error("Shopify GraphQL User Error", ['errors' => $body['errors']]);
            throw new \Exception("GraphQL Error: " . json_encode($body['errors']));
        }

        return $body['data'];
    }

    // ==========================================
    // 1️⃣ Fulfillment (REST)
    // ==========================================

    /**
     * Get all locations (needed for fulfillment)
     */
    public function getLocations()
    {
        return $this->get('locations.json')['locations'] ?? [];
    }

    /**
     * Create Fulfillment for an Order
     */
    public function createFulfillment(string $shopifyOrderId, ?string $trackingNumber = null, ?string $trackingCompany = null)
    {
        // 1. Get Fulfillment Orders
        $response = $this->get("orders/{$shopifyOrderId}/fulfillment_orders.json");
        $fulfillmentOrders = $response['fulfillment_orders'] ?? [];
        
        Log::info("Fulfillment Orders for {$shopifyOrderId}:", $fulfillmentOrders);

        if (empty($fulfillmentOrders)) {
            // Fallback: Try looking for any fulfillment data or throw specific error
            throw new \Exception("No fulfillment orders returned from Shopify.");
        }

        // Filter for fulfillable statuses
        $targetFulfillmentOrder = collect($fulfillmentOrders)->first(function ($fo) {
            return in_array($fo['status'], ['open', 'in_progress']);
        });
        
        if (!$targetFulfillmentOrder) {
             throw new \Exception("No open or in_progress fulfillment orders found. Statuses: " . collect($fulfillmentOrders)->pluck('status')->implode(', '));
        }

        $fulfillmentPayload = [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    [
                        'fulfillment_order_id' => $targetFulfillmentOrder['id'],
                    ]
                ],
                'notify_customer' => true,
                'tracking_info' => $trackingNumber ? [
                    'number' => $trackingNumber,
                    'company' => $trackingCompany
                ] : null
            ]
        ];

        return $this->post("fulfillments.json", $fulfillmentPayload);
    }


    // ==========================================
    // 2️⃣ Order Manipulation (GraphQL)
    // ==========================================

    /**
     * Cancel Order (REST API)
     */
public function cancelOrder(string $shopifyOrderId)
{
    $payload = [
        'restock' => true,  
        'email'   => true,  
        'reason' => 'customer', // optional
    ];

    return $this->post("orders/{$shopifyOrderId}/cancel.json", $payload);
}


    /**
     * Update Order Quantity (Order Edit)
     */
    public function updateOrderQuantity(string $shopifyOrderId, string $lineItemId, int $newQuantity)
    {
        // 1. Begin Edit
        $beginMutation = <<<'GRAPHQL'
            mutation orderEditBegin($id: ID!) {
                orderEditBegin(id: $id) {
                    calculatedOrder {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $beginData = $this->graphQL($beginMutation, ['id' => "gid://shopify/Order/{$shopifyOrderId}"]);
        
        if (!empty($beginData['orderEditBegin']['userErrors'])) {
            throw new \Exception("Edit Begin Error: " . $beginData['orderEditBegin']['userErrors'][0]['message']);
        }

        $calculatedOrder = $beginData['orderEditBegin']['calculatedOrder'];
        $calculatedOrderId = $calculatedOrder['id'];
        
        // 2. Set Quantity
        // Use the original Line ID. Shopify will find the calculated line item for it.
        $setQtyMutation = <<<'GRAPHQL'
            mutation orderEditSetQuantity($id: ID!, $lineItemId: ID!, $quantity: Int!) {
                orderEditSetQuantity(id: $id, lineItemId: $lineItemId, quantity: $quantity) {
                    calculatedOrder {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $setQtyData = $this->graphQL($setQtyMutation, [
            'id' => $calculatedOrderId,
            'lineItemId' => "gid://shopify/LineItem/{$lineItemId}",
            'quantity' => $newQuantity
        ]);

        if (!empty($setQtyData['orderEditSetQuantity']['userErrors'])) {
            throw new \Exception("Set Quantity Error: " . $setQtyData['orderEditSetQuantity']['userErrors'][0]['message']);
        }

        // 3. Commit Edit
        $commitMutation = <<<'GRAPHQL'
            mutation orderEditCommit($id: ID!) {
                orderEditCommit(id: $id) {
                    order {
                         id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $commitData = $this->graphQL($commitMutation, ['id' => $calculatedOrderId]);

        if (!empty($commitData['orderEditCommit']['userErrors'])) {
            throw new \Exception("Commit Error: " . $commitData['orderEditCommit']['userErrors'][0]['message']);
        }

        return $commitData['orderEditCommit']['order'];
    }


    // ==========================================
    // 3️⃣ Refund (REST)
    // ==========================================

    /**
     * Create Refund
     * 
   
     */
    public function createRefund(string $shopifyOrderId, array $refundLineItems = [], bool $shippingRefund = false, ?string $locationId = null)
    {
        // 1. Calculate Refund (Calculates taxes/totals automatically)
        $calculatePayload = [
            'refund' => [
                'shipping' => $shippingRefund ? ['full_refund' => true] : null,
                'refund_line_items' => array_map(function($item) use ($locationId) {
                    // Inject location_id if required for restock
                    if ($locationId && isset($item['restock_type']) && $item['restock_type'] === 'return') {
                        $item['location_id'] = $locationId;
                    }
                    return $item;
                }, $refundLineItems),
            ]
        ];

        // This call is strictly to get the calculated amounts ("transactions" block)
        $calculated = $this->post("orders/{$shopifyOrderId}/refunds/calculate.json", $calculatePayload);
        $refundData = $calculated['refund'];

        // 2. Create actual refund using calculated transaction amount
        // We only care about Kind=refund transactions from the calculation
        $transactionsToCreate = [];
        foreach ($refundData['transactions'] as $transaction) {
            if ($transaction['kind'] === 'refund' || $transaction['kind'] === 'suggested_refund') {
                $transactionsToCreate[] = [
                    'parent_id' => $transaction['parent_id'],
                    'amount' => $transaction['amount'],
                    'kind' => 'refund',
                    'gateway' => $transaction['gateway'],
                ];
            }
        }

        $finalPayload = [
            'refund' => [
                'currency' => $refundData['currency'],
                'notify_customer' => true,
                'refund_line_items' => $calculatePayload['refund']['refund_line_items'],
                'transactions' => $transactionsToCreate,
            ]
        ];
        
        return $this->post("orders/{$shopifyOrderId}/refunds.json", $finalPayload);
    }
}
