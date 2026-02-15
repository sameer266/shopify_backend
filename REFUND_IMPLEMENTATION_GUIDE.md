# Refund Data Storage and Calculation Implementation

## Summary of Changes

This document explains the implementation of proper refund data storage and calculation logic in the Shopify sync system.

## Requirements Implemented

### 1. Data Storage

#### ✅ Store `refund_line_items.subtotal` into `refund_items.subtotal`
**Location:** `SyncController::saveRefunds()` - Lines 357-368

```php
$subtotal = (float) ($refundItem['subtotal'] ?? 0);
if ($subtotal === 0.0 && !empty($refundItem['subtotal_set']['shop_money']['amount'])) {
    $subtotal = (float) $refundItem['subtotal_set']['shop_money']['amount'];
}

$refund->refundItems()->updateOrCreate(
    ['shopify_line_item_id' => (string) ($refundItem['id'] ?? '')],
    [
        // ...
        'subtotal' => $subtotal, // REQUIREMENT: Store subtotal
        // ...
    ]
);
```

#### ✅ Store `order_adjustments.amount` into `refund_adjustments.amount`
**Location:** `SyncController::saveRefunds()` - Lines 382-394

```php
foreach ($refundData['order_adjustments'] ?? [] as $adj) {
    $amount = (float) ($adj['amount_set']['shop_money']['amount'] ?? $adj['amount'] ?? 0);
    
    $refund->orderAdjustments()->create([
        'shopify_adjustment_id' => (string) ($adj['id'] ?? ''),
        'kind' => $adj['kind'] ?? null,
        'reason' => $adj['reason'] ?? null,
        'amount' => $amount, // REQUIREMENT: Store amount as-is
        'tax_amount' => $taxAmount,
    ]);
}
```

#### ✅ Store `discount_allocations.amount` into `orders.total_discounts` (Always Positive)
**Location:** `SyncController::createOrder()` - Lines 195-197

```php
// Convert discount to positive value if Shopify sends it as negative
$totalDiscounts = (float) ($orderData['total_discounts'] ?? 0);
$totalDiscounts = abs($totalDiscounts); // Ensure positive value
```

**Also in:** `SyncController::saveOrderItems()` - Lines 256-257

```php
// Convert discount to positive if negative
$discountAmount = collect($item['discount_allocations'] ?? [])
    ->sum(fn($discount) => abs((float)($discount['amount'] ?? 0)));
```

### 2. Refund Calculation Formula

#### ✅ Implemented New Formula
**Location:** `SyncController::updateOrderTotals()` - Lines 400-452

```php
/**
 * Calculate total_refunds using the new formula:
 * 
 * total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
 * 
 * Where:
 * - Refund adjustments are always subtracted from items total
 * - If discount is negative, subtract it (adds to refund)
 * - If discount is positive, add it (reduces refund)
 */
```

**Formula Breakdown:**

1. **Sum refund items subtotal:** `$itemsSubtotal = $refund->refundItems->sum('subtotal')`
2. **Sum refund adjustments amount:** `$adjustmentsTotal = $refund->orderAdjustments->sum('amount')`
3. **Get discount:** `$discount = $order->total_discounts`
4. **Calculate:** `$refundAmount = $itemsSubtotal - $adjustmentsTotal ± $discount`

## Key Implementation Details

### Discount Handling

**Problem:** Shopify may send discount values as negative numbers.

**Solution:** Convert all discount values to positive when storing:

```php
// In createOrder()
$totalDiscounts = abs((float) ($orderData['total_discounts'] ?? 0));

// In saveOrderItems()
$discountAmount = collect($item['discount_allocations'] ?? [])
    ->sum(fn($discount) => abs((float)($discount['amount'] ?? 0)));
```

### Refund Adjustments

**What are they?**
- Shipping refunds (positive values - increase refund)
- Restocking fees (negative values - reduce refund)
- Refund fees (negative values - reduce refund)

**How they're handled:**
- Stored as-is (can be positive or negative)
- Always subtracted in the calculation formula
- Negative adjustments effectively reduce the total refund
- Positive adjustments effectively increase the total refund

### Example Calculation

**Scenario:**
- Refund items subtotal: Rs 1000.00
- Shipping refund (adjustment): Rs 150.00
- Restocking fee (adjustment): Rs -50.00
- Order discount: Rs 100.00 (positive)

**Calculation:**
```
total_refunds = 1000 - 150 - (-50) ± 100
              = 1000 - 150 + 50 ± 100
              = 900 ± 100
              
If discount is positive (standard case): 900 + 100 = 1000
If discount is negative: 900 - 100 = 800
```

## Files Modified

1. **SyncController.php**
   - `createOrder()` - Added discount conversion to positive
   - `saveOrderItems()` - Added discount conversion to positive
   - `saveRefunds()` - Added comments for requirements
   - `updateOrderTotals()` - Completely rewritten with new formula

## Testing Recommendations

1. **Test with orders that have:**
   - ✅ No discounts
   - ✅ Positive discounts
   - ✅ Negative discounts (if they exist in your Shopify data)
   - ✅ Refunds with shipping refunds
   - ✅ Refunds with restocking fees
   - ✅ Partial refunds
   - ✅ Full refunds

2. **Verify calculations match Shopify:**
   - Run sync
   - Compare `total_refunds` in database with Shopify admin
   - Check individual refund amounts
   - Validate discount values are positive

## Migration Path

To apply these changes to existing data:

1. **Backup your database**
   ```bash
   php artisan db:backup # or your backup command
   ```

2. **Replace the controller**
   ```bash
   # Backup old controller
   cp app/Http/Controllers/SyncController.php app/Http/Controllers/SyncController_OLD.php
   
   # Replace with new version
   cp app/Http/Controllers/SyncController_UPDATED.php app/Http/Controllers/SyncController.php
   ```

3. **Run a full sync**
   ```bash
   php artisan sync:orders
   # or trigger from the UI
   ```

4. **Validate results**
   - Check a few orders manually
   - Compare totals with Shopify
   - Run any existing validation scripts

## Troubleshooting

### Issue: Refund totals don't match Shopify

**Check:**
1. Are discounts being stored as positive? `SELECT total_discounts FROM orders WHERE total_discounts < 0;`
2. Are adjustments stored correctly? `SELECT * FROM refund_adjustments;`
3. Is the formula being applied correctly? Add logging to `updateOrderTotals()`

### Issue: Negative discount handling is wrong

**Solution:**
The current implementation stores all discounts as positive. If you need to preserve the original sign, modify the `createOrder()` method to not use `abs()`.

Then update the formula in `updateOrderTotals()` to handle both cases explicitly.

## Future Enhancements

1. **Add logging** to track discount sign conversions
2. **Add validation** to ensure refund totals never exceed order totals
3. **Create a report** showing discount distribution (positive vs negative)
4. **Add unit tests** for the refund calculation formula

## Questions or Issues?

If you encounter any issues or need clarification on the implementation, please check:

1. Shopify's refund API documentation
2. The actual values being sent by Shopify (add logging)
3. Compare with Shopify admin panel calculations
