# Refund Data Storage and Calculation - Implementation Summary

## Overview

This implementation provides proper handling of refund data from Shopify, including:
1. Correct data storage for refund items, adjustments, and discounts
2. A new calculation formula that accurately computes total refunds
3. Tools to migrate existing data

## Files Created/Modified

### 1. **SyncController_UPDATED.php** (New Controller)
- Location: `app/Http/Controllers/SyncController_UPDATED.php`
- Contains all the new logic for discount handling and refund calculation
- Ready to replace your existing `SyncController.php`

### 2. **REFUND_IMPLEMENTATION_GUIDE.md** (Documentation)
- Location: `REFUND_IMPLEMENTATION_GUIDE.md`
- Complete documentation of all changes
- Includes examples, troubleshooting, and testing recommendations

### 3. **recalculate_refunds.php** (Migration Script)
- Location: `recalculate_refunds.php`
- Script to recalculate existing refund data
- Includes validation checks

## Implementation Steps

### Step 1: Backup Your Data

```bash
# Backup database
mysqldump -u username -p database_name > backup_before_refund_update.sql

# Or use Laravel backup if available
php artisan backup:run
```

### Step 2: Review the Changes

1. Open `SyncController_UPDATED.php` and review the changes
2. Read `REFUND_IMPLEMENTATION_GUIDE.md` for detailed explanations
3. Pay special attention to these methods:
   - `createOrder()` - Discount conversion
   - `saveOrderItems()` - Item discount handling
   - `saveRefunds()` - Refund item and adjustment storage
   - `updateOrderTotals()` - New calculation formula

### Step 3: Apply the Changes

```bash
# Backup old controller
cp app/Http/Controllers/SyncController.php app/Http/Controllers/SyncController_BACKUP.php

# Apply new controller
cp app/Http/Controllers/SyncController_UPDATED.php app/Http/Controllers/SyncController.php
```

### Step 4: Recalculate Existing Data

```bash
# Run the recalculation script
php recalculate_refunds.php
```

This will:
- Convert negative discounts to positive values
- Recalculate all refund amounts using the new formula
- Update order total_refunds
- Provide validation report

### Step 5: Validate Results

1. **Check a few orders manually:**
   ```sql
   SELECT 
       o.order_number,
       o.total_price,
       o.total_discounts,
       o.total_refunds,
       (SELECT SUM(subtotal) FROM refund_items ri 
        JOIN refunds r ON ri.refund_id = r.id 
        WHERE r.order_id = o.id) as items_total,
       (SELECT SUM(amount) FROM refund_adjustments ra 
        JOIN refunds r ON ra.refund_id = r.id 
        WHERE r.order_id = o.id) as adjustments_total
   FROM orders o
   WHERE o.total_refunds > 0
   LIMIT 10;
   ```

2. **Compare with Shopify:**
   - Log into Shopify admin
   - Check a few orders with refunds
   - Compare the refund totals

3. **Run validation queries:**
   ```sql
   -- Check for negative discounts
   SELECT COUNT(*) FROM orders WHERE total_discounts < 0;
   
   -- Check for negative refunds
   SELECT COUNT(*) FROM refunds WHERE total_amount < 0;
   
   -- Check for refunds exceeding order total
   SELECT COUNT(*) FROM orders WHERE total_refunds > total_price;
   ```

### Step 6: Test with New Data

1. Make a test refund in Shopify
2. Trigger a sync in your app
3. Verify the refund data is stored correctly
4. Confirm the calculations match Shopify

## Key Changes Summary

### 1. Discount Storage ✅
- **Before:** Stored as-is (could be negative)
- **After:** Always stored as positive value using `abs()`
- **Location:** `createOrder()` and `saveOrderItems()`

### 2. Refund Item Subtotal ✅
- **Requirement:** Store `refund_line_items.subtotal` → `refund_items.subtotal`
- **Status:** Already implemented, now with clear documentation
- **Location:** `saveRefunds()`

### 3. Refund Adjustments ✅
- **Requirement:** Store `order_adjustments.amount` → `refund_adjustments.amount`
- **Status:** Already implemented, stores values as-is (can be positive or negative)
- **Location:** `saveRefunds()`

### 4. Refund Calculation Formula ✅
- **Formula:** `total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount`
- **New Implementation:** Complete rewrite of `updateOrderTotals()`
- **Handles:**
  - Item subtotals (always positive)
  - Adjustments (can be positive or negative, always subtracted)
  - Discounts (now always positive, handling depends on business logic)

## Formula Explanation

```
total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
```

### Components:

1. **SUM(refund_items.subtotal)**: Total of all refunded items
   - Example: 3 items refunded at Rs 500 each = Rs 1500

2. **SUM(refund_adjustments.amount)**: Sum of adjustments (always subtracted)
   - Positive adjustments (shipping refunds): increase the refund
   - Negative adjustments (fees): decrease the refund
   - Example: +Rs 150 (shipping) -Rs 50 (fee) = Rs 100
   - In formula: 1500 - 100 = Rs 1400

3. **± discount**: Discount handling
   - If discount is negative: subtract it (adds to refund)
   - If discount is positive: add it (reduces refund)
   - Example with Rs 100 discount: 1400 + 100 = Rs 1500

## Testing Checklist

- [ ] Backup database completed
- [ ] Old controller backed up
- [ ] New controller applied
- [ ] Recalculation script executed successfully
- [ ] No negative discounts found
- [ ] No negative refunds found
- [ ] No refunds exceeding order totals
- [ ] Manual spot check matches Shopify (minimum 5 orders)
- [ ] Test refund created in Shopify
- [ ] Test refund synced correctly
- [ ] Calculations verified

## Troubleshooting

### Issue: Refund totals don't match Shopify

**Solution:**
1. Check if discount handling is correct for your use case
2. Review the `updateOrderTotals()` method
3. Add logging to see intermediate values:
   ```php
   Log::info('Refund Calculation', [
       'order' => $order->order_number,
       'items_subtotal' => $itemsSubtotal,
       'adjustments' => $adjustmentsTotal,
       'discount' => $discount,
       'final_amount' => $refundAmount
   ]);
   ```

### Issue: Some orders still have issues

**Solution:**
1. Run this query to find problematic orders:
   ```sql
   SELECT o.*, 
          (SELECT COUNT(*) FROM refunds WHERE order_id = o.id) as refund_count
   FROM orders o
   WHERE o.total_refunds > o.total_price
   OR o.total_refunds < 0
   OR o.total_discounts < 0;
   ```
2. Manually inspect these orders in Shopify
3. Compare data structures

### Issue: Discount logic seems wrong

**Solution:**
The discount handling in `updateOrderTotals()` may need adjustment based on your business logic. Review lines 431-445 in the new controller and modify as needed.

## Important Notes

1. **Discount Sign:** All discounts are now stored as positive values. The original sign from Shopify is lost. If you need to preserve it, remove the `abs()` calls and adjust the formula accordingly.

2. **Adjustment Types:** 
   - Shipping refunds are typically positive
   - Restocking fees are typically negative
   - Both are always subtracted in the formula (negative subtraction becomes addition)

3. **Business Logic:** The exact discount handling may vary based on your business needs. Review and adjust the commented section in `updateOrderTotals()` if needed.

## Support

If you encounter any issues:

1. Check the logs: `storage/logs/laravel.log`
2. Review the documentation: `REFUND_IMPLEMENTATION_GUIDE.md`
3. Compare with Shopify data structures
4. Test with a small subset of orders first

## Rollback Plan

If something goes wrong:

```bash
# Restore database
mysql -u username -p database_name < backup_before_refund_update.sql

# Restore old controller
cp app/Http/Controllers/SyncController_BACKUP.php app/Http/Controllers/SyncController.php

# Clear cache
php artisan cache:clear
php artisan config:clear
```

## Success Criteria

✅ All discounts are positive
✅ All refunds are non-negative
✅ No refunds exceed order totals
✅ Refund totals match Shopify (within rounding)
✅ New syncs work correctly
✅ Formula handles all edge cases

---

**Version:** 1.0
**Date:** February 14, 2026
**Status:** Ready for Implementation
