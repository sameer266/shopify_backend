<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiServices
{
    private string $domain;
    private string $token;
    private string $version;
    private string $baseUrl;

    public function __construct()
    {
        $this->domain = env('SHOPIFY_STORE_DOMAIN');
        $this->token = env('SHOPIFY_ACCESS_TOKEN');
        $this->version = env('SHOPIFY_API_VERSION', '2025-01');
        $this->baseUrl = "https://{$this->domain}/admin/api/{$this->version}";
    }

    // ==========================================
    // Core API Methods
    // ==========================================

    /**
     * Make a REST GET request
     */
    public function get(string $endpoint, array $params = [])
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        $response = Http::withHeaders($this->getHeaders())
            ->get($url, $params);

        return $this->handleRestResponse($response, "GET {$endpoint}");
    }

    /**
     * Make a REST POST request
     */
    public function post(string $endpoint, array $data = [])
    {
        $url = "{$this->baseUrl}/{$endpoint}";

        $response = Http::withHeaders($this->getHeaders())
            ->post($url, $data);

        return $this->handleRestResponse($response, "POST {$endpoint}");
    }

    /**
     * Make a GraphQL request
     */
    public function graphQL(string $query, array $variables = [])
    {
        $url = "{$this->baseUrl}/graphql.json";

        $response = Http::withHeaders($this->getHeaders())
            ->post($url, [
                'query' => $query,
                'variables' => $variables,
            ]);

        return $this->handleGraphQLResponse($response);
    }

    // ==========================================
    // Locations
    // ==========================================

    public function getLocations()
    {
        $response = $this->get('locations.json');
        return $response['locations'] ?? [];
    }

    // ==========================================
    // Fulfillment
    // ==========================================

    public function createFulfillment(
        string $shopifyOrderId,
        ?string $trackingNumber = null,
        ?string $trackingCompany = null
    ) {
        $fulfillmentOrder = $this->getOpenFulfillmentOrder($shopifyOrderId);

        $payload = [
            'fulfillment' => [
                'line_items_by_fulfillment_order' => [
                    ['fulfillment_order_id' => $fulfillmentOrder['id']]
                ],
                'notify_customer' => true,
            ]
        ];

        // Add tracking info if provided
        if ($trackingNumber) {
            $payload['fulfillment']['tracking_info'] = [
                'number' => $trackingNumber,
                'company' => $trackingCompany
            ];
        }

        return $this->post('fulfillments.json', $payload);
    }

    // ==========================================
    // Order Operations
    // ==========================================

    public function cancelOrder(string $shopifyOrderId)
    {
        return $this->post("orders/{$shopifyOrderId}/cancel.json", [
            'restock' => true,
        ]);
    }

    public function updateOrderQuantity(string $shopifyOrderId, string $lineItemId, int $newQuantity)
    {
        // Step 1: Begin order edit
        $calculatedOrderId = $this->beginOrderEdit($shopifyOrderId);

        // Step 2: Update quantity
        $this->setOrderItemQuantity($calculatedOrderId, $lineItemId, $newQuantity);

        // Step 3: Commit changes
        return $this->commitOrderEdit($calculatedOrderId);
    }

    // ==========================================
    // Refunds
    // ==========================================

    public function createRefund(
        string $shopifyOrderId,
        array $refundLineItems = [],
        bool $shippingRefund = false,
        ?string $locationId = null,
        bool $notifyCustomer = false
    ) {
        // Add location to each item for restocking
        if ($locationId) {
            $refundLineItems = $this->addLocationToItems($refundLineItems, $locationId);
        }

        $payload = [
            'refund' => [
                'refund_line_items' => $refundLineItems,
                'notify' => $notifyCustomer,
            ],
        ];

        // Add shipping refund if requested
        if ($shippingRefund) {
            $payload['refund']['shipping'] = ['full_refund' => true];
        }

        return $this->post("orders/{$shopifyOrderId}/refunds.json", $payload);
    }

    // ==========================================
    // Private Helper Methods
    // ==========================================

    private function getHeaders(): array
    {
        return [
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ];
    }

    private function handleRestResponse($response, string $context)
    {
        if ($response->failed()) {
            $error = $response->json()['errors'] ?? $response->body();
            Log::error("Shopify REST Error: {$context}", ['body' => $response->body()]);
            throw new \Exception("Shopify API Error: " . (is_array($error) ? json_encode($error) : $error));
        }

        return $response->json();
    }

    private function handleGraphQLResponse($response)
    {
        if ($response->failed()) {
            Log::error('Shopify GraphQL Error', ['body' => $response->body()]);
            throw new \Exception("Shopify GraphQL Error: " . $response->body());
        }

        $body = $response->json();

        if (isset($body['errors'])) {
            Log::error('Shopify GraphQL User Error', ['errors' => $body['errors']]);
            throw new \Exception("GraphQL Error: " . json_encode($body['errors']));
        }

        return $body['data'];
    }

    private function getOpenFulfillmentOrder(string $shopifyOrderId): array
    {
        $response = $this->get("orders/{$shopifyOrderId}/fulfillment_orders.json");
        $fulfillmentOrders = $response['fulfillment_orders'] ?? [];

        if (empty($fulfillmentOrders)) {
            throw new \Exception('No fulfillment orders found for this order.');
        }

        // Find first open or in-progress fulfillment order
        $openOrder = collect($fulfillmentOrders)->first(function ($order) {
            return in_array($order['status'], ['open', 'in_progress']);
        });

        if (!$openOrder) {
            $statuses = collect($fulfillmentOrders)->pluck('status')->implode(', ');
            throw new \Exception("No open fulfillment orders. Current statuses: {$statuses}");
        }

        return $openOrder;
    }

    private function beginOrderEdit(string $shopifyOrderId): string
    {
        $data = $this->graphQL('
            mutation orderEditBegin($id: ID!) {
                orderEditBegin(id: $id) {
                    calculatedOrder { id }
                    userErrors { field message }
                }
            }
        ', ['id' => "gid://shopify/Order/{$shopifyOrderId}"]);

        $errors = $data['orderEditBegin']['userErrors'] ?? [];
        
        if (!empty($errors)) {
            throw new \Exception("Begin Edit Error: " . $errors[0]['message']);
        }

        return $data['orderEditBegin']['calculatedOrder']['id'];
    }

    private function setOrderItemQuantity(string $calculatedOrderId, string $lineItemId, int $quantity): void
    {
        $data = $this->graphQL('
            mutation orderEditSetQuantity($id: ID!, $lineItemId: ID!, $quantity: Int!) {
                orderEditSetQuantity(id: $id, lineItemId: $lineItemId, quantity: $quantity) {
                    calculatedOrder { id }
                    userErrors { field message }
                }
            }
        ', [
            'id' => $calculatedOrderId,
            'lineItemId' => "gid://shopify/LineItem/{$lineItemId}",
            'quantity' => $quantity
        ]);

        $errors = $data['orderEditSetQuantity']['userErrors'] ?? [];
        
        if (!empty($errors)) {
            throw new \Exception("Set Quantity Error: " . $errors[0]['message']);
        }
    }

    private function commitOrderEdit(string $calculatedOrderId): array
    {
        $data = $this->graphQL('
            mutation orderEditCommit($id: ID!) {
                orderEditCommit(id: $id) {
                    order { id }
                    userErrors { field message }
                }
            }
        ', ['id' => $calculatedOrderId]);

        $errors = $data['orderEditCommit']['userErrors'] ?? [];
        
        if (!empty($errors)) {
            throw new \Exception("Commit Error: " . $errors[0]['message']);
        }

        return $data['orderEditCommit']['order'];
    }

    private function addLocationToItems(array $items, string $locationId): array
    {
        return array_map(function ($item) use ($locationId) {
            $item['location_id'] = $locationId;
            return $item;
        }, $items);
    }
}