<?php
/**
 * Update Customers with Shopify Created At
 * 
 * This script fetches the created_at date from Shopify for all existing customers
 * and updates the shopify_created_at field in the database.
 * 
 * Usage: php update_customer_dates.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Load Shopify credentials
$shopifyDomain = env('SHOPIFY_STORE_DOMAIN', '');
$accessToken = env('SHOPIFY_ACCESS_TOKEN', '');
$apiVersion = env('SHOPIFY_API_VERSION', '2026-01');

if (!$shopifyDomain || !$accessToken) {
    echo "❌ ERROR: Shopify configuration missing in .env file\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════\n";
echo "  UPDATE CUSTOMER SHOPIFY CREATED AT DATES\n";
echo "═══════════════════════════════════════════════════\n\n";

try {
    $customers = Customer::whereNotNull('shopify_customer_id')->get();
    
    echo "Found " . $customers->count() . " customers to update...\n\n";
    
    $updated = 0;
    $failed = 0;
    $skipped = 0;
    
    foreach ($customers as $customer) {
        // Skip if already has shopify_created_at
        if ($customer->shopify_created_at) {
            $skipped++;
            continue;
        }
        
        try {
            // Fetch customer from Shopify
            $url = "https://{$shopifyDomain}/admin/api/{$apiVersion}/customers/{$customer->shopify_customer_id}.json";
            
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->timeout(30)->get($url);
            
            if ($response->successful()) {
                $shopifyData = $response->json();
                $createdAt = $shopifyData['customer']['created_at'] ?? null;
                
                if ($createdAt) {
                    $customer->update(['shopify_created_at' => $createdAt]);
                    $updated++;
                    
                    echo sprintf(
                        "✓ %-30s Created: %s\n",
                        $customer->full_name ?: $customer->email,
                        \Carbon\Carbon::parse($createdAt)->format('Y-m-d H:i:s')
                    );
                } else {
                    $failed++;
                    echo "  ✗ {$customer->full_name}: No created_at in response\n";
                }
            } else {
                $failed++;
                echo "  ✗ {$customer->full_name}: API error {$response->status()}\n";
            }
            
            // Rate limit: sleep for 0.5 seconds between requests
            usleep(500000);
            
        } catch (\Exception $e) {
            $failed++;
            echo "  ✗ {$customer->full_name}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n═══════════════════════════════════════════════════\n";
    echo "    SUMMARY\n";
    echo "═══════════════════════════════════════════════════\n";
    echo "Total Customers:         " . $customers->count() . "\n";
    echo "Successfully Updated:    " . $updated . "\n";
    echo "Already Had Date:        " . $skipped . "\n";
    echo "Failed:                  " . $failed . "\n";
    echo "═══════════════════════════════════════════════════\n\n";
    
    if ($updated > 0) {
        echo "✅ SUCCESS! Customer dates updated.\n";
        echo "   New customer calculations will now use Shopify signup dates.\n\n";
    } else {
        echo "ℹ️  No updates needed.\n\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}