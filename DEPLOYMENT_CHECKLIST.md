# ✅ DEPLOYMENT CHECKLIST - All Issues Fixed

## Issues That Were Corrected:

### 1. ✅ WebhookController.php - Duplicate Catch Blocks
**Line 38-40:** Removed duplicate `catch (\Throwable $e)` block

### 2. ✅ SyncController.php - Missing Refund Data Request
**Line 83:** Added `fields` parameter to explicitly request refund data from Shopify

### 3. ✅ DashboardController.php - Incorrect Calculation Logic
**Lines 108-177:** Simplified to match Shopify's exact formula

### 4. ✅ Created DiagnosticController.php
New debugging tool to identify data mismatches

---

## Files Modified:

1. ✅ `app/Http/Controllers/WebhookController.php` - Fixed duplicate catch
2. ✅ `app/Http/Controllers/SyncController.php` - Added fields parameter + logging
3. ✅ `app/Http/Controllers/DashboardController.php` - Fixed calculations
4. ✅ `app/Http/Controllers/DiagnosticController.php` - NEW FILE

---

## Deployment Steps:

### Step 1: Add Route
Open `routes/web.php` and add:
```php
use App\Http\Controllers\DiagnosticController;

Route::get('/diagnostic/check-data', [DiagnosticController::class, 'checkDataIntegrity'])
    ->middleware('auth');
```

### Step 2: Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 3: Test the Application
```bash
php artisan serve
```
Visit your application and make sure no syntax errors

### Step 4: Check Logs for Errors
```bash
tail -f storage/logs/laravel.log
```

### Step 5: Prepare for Re-Sync
Update `.env`:
```
SHOPIFY_FULL_SYNC=true
```

### Step 6: Backup Database (IMPORTANT!)
```bash
# Using mysqldump
mysqldump -u your_user -p your_database > backup_before_sync.sql

# OR use phpMyAdmin to export
```

### Step 7: Run Full Sync
1. Go to your dashboard
2. Click "Sync Orders" button
3. Wait for completion (monitor logs)
4. You should see in logs:
   - "Fetched orders from Shopify" with refund data
   - "Saving refunds for order" messages
   - "Refund totals calculated" messages

### Step 8: Check Diagnostic
Visit: `http://your-domain/diagnostic/check-data?range=30d`

Should show:
```json
{
  "totals": {
    "gross_sales_sum": 15250.00,
    "discounts_sum": 380.00,
    "refunds_sum": 4521.00,
    "net_sales_calculated": 10349.00
  }
}
```

### Step 9: Verify Dashboard
Dashboard should now show:
- ✅ Gross Sales: NPR 15,250.00
- ✅ Discounts: NPR 380.00  
- ✅ Returns: NPR 4,521.00
- ✅ Net Sales: NPR 10,349.00

---

## Testing Webhooks

After sync, test webhooks by:

1. Create a test order in Shopify
2. Check logs for "Webhook received"
3. Verify order appears in your dashboard
4. Try updating the order in Shopify
5. Verify changes appear in your dashboard

---

## If Numbers Still Don't Match:

### Debug Step 1: Check Logs
```bash
grep "first_order_has_refunds" storage/logs/laravel.log
```
Should return `true` if refunds are being fetched

### Debug Step 2: Check Database
Run in MySQL/phpMyAdmin:
```sql
SELECT 
    financial_status,
    COUNT(*) as count,
    SUM(subtotal_price) as gross,
    SUM(total_discounts) as discounts,
    SUM(total_refunds) as refunds
FROM orders
WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY financial_status;
```

### Debug Step 3: Compare Individual Orders
Check a specific order that has a refund in Shopify:
```sql
SELECT 
    o.order_number,
    o.financial_status,
    o.subtotal_price,
    o.total_discounts,
    o.total_refunds,
    COUNT(r.id) as refund_count,
    SUM(r.total_amount) as refund_total
FROM orders o
LEFT JOIN refunds r ON r.order_id = o.id
WHERE o.order_number = 'YOUR_ORDER_NUMBER'
GROUP BY o.id;
```

### Debug Step 4: Check Shopify API Response
Add temporary logging in SyncController.php (line 96):
```php
Log::info('Shopify order sample', [
    'order_number' => $orders[0]['order_number'] ?? 'N/A',
    'subtotal' => $orders[0]['total_line_items_price'] ?? 0,
    'discounts' => $orders[0]['total_discounts'] ?? 0,
    'refunds_count' => count($orders[0]['refunds'] ?? []),
    'first_refund_amount' => $orders[0]['refunds'][0]['transactions'][0]['amount'] ?? 'N/A'
]);
```

---

## Common Issues & Solutions:

### Issue: "Class DiagnosticController not found"
**Solution:** Run `composer dump-autoload`

### Issue: Webhooks not working
**Solution:** 
1. Check `.env` has `SHOPIFY_WEBHOOK_SECRET`
2. Verify webhook URL in Shopify admin
3. Check firewall/server allows incoming webhooks

### Issue: Sync times out
**Solution:**
1. Increase PHP max_execution_time
2. Sync in smaller batches (reduce limit from 250 to 50)

### Issue: Numbers still don't match
**Solution:**
1. Share diagnostic output
2. Share sample order JSON from Shopify
3. Check if any orders have status not in: paid, partially_paid, partially_refunded, refunded

---

## Rollback Plan (If Needed):

If something goes wrong:

1. **Restore database:**
```bash
mysql -u your_user -p your_database < backup_before_sync.sql
```

2. **Revert code changes:**
```bash
git checkout HEAD -- app/Http/Controllers/
```

3. **Clear cache again:**
```bash
php artisan config:clear
php artisan cache:clear
```

---

## Success Criteria:

✅ Dashboard shows same numbers as Shopify
✅ Diagnostic shows correct totals
✅ No PHP errors in logs
✅ Webhooks working correctly
✅ New orders sync automatically

---

## Need Help?

If you encounter issues:

1. Share the output of `/diagnostic/check-data`
2. Share relevant lines from `storage/logs/laravel.log`
3. Share a specific order number that doesn't match
4. Share Shopify's analytics for the same date range

This will help identify any remaining issues quickly.
