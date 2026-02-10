<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use App\Models\Order;
use App\Models\Customer;

class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_create_webhook_processes_correctly()
    {
        // Mock config for secret
        Config::set('shopify.webhook_secret', 'test_secret');

        $payload = [
            'id' => 1234567890,
            'order_number' => '1001',
            'email' => 'test@example.com',
            'total_price' => '100.00',
            'subtotal_price' => '90.00',
            'total_tax' => '10.00',
            'currency' => 'USD',
            'customer' => [
                'id' => 987654321,
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'verified_email' => true,
                'state' => 'enabled',
                'tags' => 'vip, wholesale',
                'addresses' => [
                    [
                        'address1' => '123 Test St',
                        'city' => 'Test City',
                        'country' => 'Test Country'
                    ]
                ]
            ],
            'line_items' => [
                [
                    'id' => 555555,
                    'price' => '50.00',
                    'quantity' => 1,
                    'title' => 'Test Product',
                    'product_id' => 444444
                ]
            ]
        ];

        $content = json_encode($payload);
        $hmac = base64_encode(hash_hmac('sha256', $content, 'test_secret', true));

        $response = $this->withHeaders([
            'X-Shopify-Hmac-Sha256' => $hmac,
            'Content-Type' => 'application/json',
        ])->post('/webhook/orders-create', $payload);

        $response->assertStatus(200);

        // Verify Database
        $this->assertDatabaseHas('orders', [
            'shopify_order_id' => '1234567890',
            'order_number' => '1001',
            'email' => 'test@example.com'
        ]);

        $this->assertDatabaseHas('customers', [
            'shopify_customer_id' => '987654321',
            'email' => 'test@example.com',
            'verified_email' => 1,
            'tags' => 'vip, wholesale'
        ]);
        
        // Check JSON field for addresses
        $customer = Customer::where('shopify_customer_id', '987654321')->first();
        $this->assertNotNull($customer->addresses);
        $this->assertStringContainsString('123 Test St', $customer->addresses);
    }
}
