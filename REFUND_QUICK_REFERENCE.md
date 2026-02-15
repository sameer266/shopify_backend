# Refund Calculation Quick Reference

## Formula

```
total_refunds = SUM(refund_items.subtotal) - SUM(refund_adjustments.amount) ± discount
```

## Data Storage Rules

### 1. Refund Items
**Source:** `refund_line_items.subtotal` (Shopify)  
**Destination:** `refund_items.subtotal` (Database)  
**Processing:** Store as-is (always positive)

### 2. Refund Adjustments
**Source:** `order_adjustments.amount` (Shopify)  
**Destination:** `refund_adjustments.amount` (Database)  
**Processing:** Store as-is (can be positive or negative)

### 3. Order Discounts
**Source:** `discount_allocations.amount` (Shopify)  
**Destination:** `orders.total_discounts` (Database)  
**Processing:** Convert to positive using `abs()` if negative

## Calculation Steps

1. **Sum refund items:** Add all `refund_items.subtotal`
2. **Sum adjustments:** Add all `refund_adjustments.amount`
3. **Subtract adjustments:** `items_total - adjustments_total`
4. **Handle discount:** Apply based on sign (if needed)
5. **Round and validate:** Ensure result is non-negative

## Example Calculation

### Scenario
- Item 1: Rs 500.00
- Item 2: Rs 300.00
- Shipping refund (adjustment): Rs 150.00
- Restocking fee (adjustment): Rs -50.00
- Order discount: Rs 100.00

### Calculation
```
Items Subtotal:        500 + 300 = 800.00
Adjustments Total:     150 + (-50) = 100.00
Before Discount:       800 - 100 = 700.00
After Discount:        700 ± 100 = 600.00 or 800.00 (depends on logic)
```

## Adjustment Types

### Positive Adjustments (Increase Refund)
- Shipping refunds
- Additional compensation
- Service fees refunded

### Negative Adjustments (Decrease Refund)
- Restocking fees
- Processing fees
- Return shipping costs

**Important:** All adjustments are **subtracted** in the formula.
- Positive adjustment: 800 - 150 = 650 (refund increases)
- Negative adjustment: 800 - (-50) = 850 (refund decreases)

## Files and Scripts

### Implementation Files
- `SyncController_UPDATED.php` - New controller with formula
- `REFUND_IMPLEMENTATION_GUIDE.md` - Detailed documentation
- `REFUND_IMPLEMENTATION_SUMMARY.md` - Step-by-step guide

### Utility Scripts
- `recalculate_refunds.php` - Migrate existing data
- `validate_refunds.php` - Verify calculations
- `fix_refunds_correct.php` - Old fix script (reference)

## Code Locations

### createOrder()
```php
// Line ~195
$totalDiscounts = abs((float) ($orderData['total_discounts'] ?? 0));
```

### saveOrderItems()
```php
// Line ~256
$discountAmount = collect($item['discount_allocations'] ?? [])
    ->sum(fn($discount) => abs((float)($discount['amount'] ?? 0)));
```

### saveRefunds()
```php
// Line ~357 - Store subtotal
'subtotal' => $subtotal,

// Line ~389 - Store adjustment amount
'amount' => $amount,
```

### updateOrderTotals()
```php
// Line ~416-445 - Complete calculation formula
$itemsSubtotal = $refund->refundItems->sum('subtotal');
$adjustmentsTotal = $refund->orderAdjustments->sum('amount');
$refundAmount = $itemsSubtotal - $adjustmentsTotal;
```

## Common Commands

### Recalculate All Refunds
```bash
php recalculate_refunds.php
```

### Validate All Refunds
```bash
php validate_refunds.php
```

### Validate Specific Order
```bash
php validate_refunds.php ORDER_NUMBER
```

### Check for Issues
```sql
-- Negative discounts
SELECT COUNT(*) FROM orders WHERE total_discounts < 0;

-- Negative refunds
SELECT COUNT(*) FROM refunds WHERE total_amount < 0;

-- Refunds exceeding order total
SELECT COUNT(*) FROM orders WHERE total_refunds > total_price;
```

## Validation Checklist

- [ ] All discounts are positive (`abs()` applied)
- [ ] All refund amounts are non-negative
- [ ] No refunds exceed order totals
- [ ] Items subtotal calculated correctly
- [ ] Adjustments summed correctly
- [ ] Formula applied correctly
- [ ] Results match Shopify within Rs 0.01

## Troubleshooting

### Mismatch with Shopify
1. Check discount handling logic
2. Verify adjustment signs are correct
3. Add logging to see intermediate values
4. Compare raw Shopify data

### Negative Values
1. Ensure `abs()` is used for discounts
2. Check for formula errors
3. Validate adjustment types

### Excessive Refunds
1. Check for duplicate refund entries
2. Verify adjustment calculations
3. Review business logic for edge cases

## Important Notes

⚠️ **Discount Sign:** Stored as positive, original sign lost  
⚠️ **Adjustments:** Can be positive or negative  
⚠️ **Formula:** Always subtract adjustments  
⚠️ **Business Logic:** May need customization for discount handling

## Version Info

**Version:** 1.0  
**Last Updated:** February 14, 2026  
**Compatible With:** Laravel 10+, Shopify API 2025-01

---

For detailed information, see `REFUND_IMPLEMENTATION_GUIDE.md`
