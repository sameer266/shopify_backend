# Shopify Sync Data Mismatch - Issues & Fixes

## Current Issue
**Your Dashboard shows:**
- Gross Sales: NPR 13,600.00
- Net Sales: NPR 9,091.55

**Shopify shows:**
- Gross Sales: NPR 15,250.00
- Discounts: -NPR 380.00
- Returns: -NPR 4,521.00
- Net Sales: NPR 10,349.00

**Difference:**
- Gross Sales: NPR 1,650.00 short
- Net Sales: NPR 1,257.45 short

---

## Root Causes Identified

### 1. **SyncController - Refunds Field Not Requested** ✅ FIXED
**Problem:** The `fetchAllOrdersFromShopify()` method doesn't explicitly request refund data.

**Location:** `SyncController.php` line 80-86

**Original Code:**
```php
$params = [
    'limit' => 250,
    'status' => 'any',
];
```

**Fixed Code:**
```php
$params = [
    'limit' => 250,
    'status' => 'any',
    'fields' => 'id,order_number,customer,email,financial_status,fulfillment_status,total_price,subtotal_price,total_line_items_price,total_discounts,total_tax,currency,processed_at,closed_at,cancelled_at,cancel_reason,shipping_address,billing_address,note,line_items,fulfillments,refunds',
];
```

**Why this matters:** Shopify's API may not include nested refund data unless you explicitly request it via the `fields` parameter.

---

### 2. **Potential Issues to Check**

#### A. **Database Column vs Shopify Field Mapping**
Check if these are correctly mapped:

| Database Column | Shopify Field | Current Mapping |
|----------------|---------------|-----------------|
| `subtotal_price` | `total_line_items_price` | ✅ Correct (line 233) |
| `total_discounts` | `total_discounts` | ✅ Correct (line 234) |
| `total_price` | `total_price` | ✅ Correct (line 232) |

#### B. **Refund Calculation**
The refunds are calculated from transactions:
```php
// Line 410-416
foreach ($refundData['transactions'] ?? [] as $transaction) {
    if (($transaction['kind'] ?? '') === 'refund' && 
        ($transaction['status'] ?? '') === 'success') {
        $totalRefunded += (float) ($transaction['amount'] ?? 0);
    }
}
```

**Potential Issue:** Are all refund transactions included in the Shopify order data?

---

## How to Diagnose

### Step 1: Add Diagnostic Route
Add this to `routes/web.php`:

```php
Route::get('/diagnostic/check-data', [DiagnosticController::class, 'checkDataIntegrity']);
```

### Step 2: Run the Diagnostic
Visit: `http://your-domain/diagnostic/check-data?range=30d`

This will show you:
- All orders in the date range
- Their gross sales, discounts, refunds
- Payment information
- Any mismatches

### Step 3: Re-sync Data
After adding the `fields` parameter:

1. Set `SHOPIFY_FULL_SYNC=true` in your `.env`
2. Click the "Sync Orders" button in your dashboard
3. Wait for sync to complete
4. Check if the numbers now match

---

## Additional Checks Needed

### Check 1: Verify Shopify API Response
Add logging to see what Shopify actually returns:

```php
// In SyncController.php, line 96 (after fetching orders)
Log::info('Sample order data', [
    'order' => $orders[0] ?? 'No orders',
    'has_refunds' => isset($orders[0]['refunds']),
    'refund_count' => count($orders[0]['refunds'] ?? [])
]);
```

### Check 2: Database Query Test
Run this in `php artisan tinker`:

```php
use App\Models\Order;
use Carbon\Carbon;

$startDate = now()->subDays(30)->startOfDay();
$endDate = now();

$orders = Order::whereBetween('processed_at', [$startDate, $endDate])
    ->whereIn('financial_status', ['paid', 'partially_paid', 'partially_refunded', 'refunded'])
    ->get();

echo "Order Count: " . $orders->count() . "\n";
echo "Gross Sales: " . $orders->sum('subtotal_price') . "\n";
echo "Discounts: " . $orders->sum('total_discounts') . "\n";
echo "Refunds (column): " . $orders->sum('total_refunds') . "\n";
```

### Check 3: Webhook Data
If using webhooks, check logs to ensure webhooks are firing:

```bash
tail -f storage/logs/laravel.log | grep "Webhook"
```

---

## Expected Outcome After Fix

After re-syncing with the corrected code:
- Gross Sales should be: **NPR 15,250.00**
- Discounts should be: **NPR 380.00**
- Returns should be: **NPR 4,521.00**
- Net Sales should be: **NPR 10,349.00**

---

## Files Modified

1. ✅ `SyncController.php` - Added `fields` parameter to API request
2. ✅ `DashboardController.php` - Fixed calculation to match Shopify
3. ✅ `DiagnosticController.php` - Created new diagnostic tool

---

## Next Steps

1. **Deploy the fixed SyncController**
2. **Add the diagnostic route**
3. **Run a full re-sync** (SHOPIFY_FULL_SYNC=true)
4. **Check diagnostic output** to verify data integrity
5. **Compare dashboard numbers with Shopify**

If numbers still don't match after re-sync, run the diagnostic and share the output for further investigation.


here check shopify sync data  and webhook response correct data  and field the ync api can contain retrun , refunds , dsicount allocation handle that alos creat new table fro that discount allocation and show in order details in correct way make sure calculation and data are correctly calclulate and remove unsued field and make syntax simple