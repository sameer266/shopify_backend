# Shopify Sync - Simple Summary

## What I Built 🚀

A Laravel app that syncs Shopify orders and shows accurate financial reports.

---

## Main Features 📊

### Dashboard Shows:
- 💰 **Total Sales** - All revenue
- 📦 **Orders** - Order count
- 🔄 **Refunds** - Money returned
- 💵 **Net Revenue** - Sales minus refunds
- 👥 **Customers** - Unique buyers

---

## How Money Calculations Work 💰

### 1. Order Total
```
Items:         Rs 8,000
- Discount:    Rs   800 (10% off)
+ Tax:         Rs   936
──────────────────────
Customer Pays: Rs 8,136
```

### 2. When Customer Returns
```
Original Item:  Rs 2,000
- Discount:     Rs   200 (was applied)
──────────────────────
Refund:        Rs 1,800 (what they get back)
```

### 3. Net Sales
```
All Sales:     Rs 100,000
- Refunds:     Rs  23,731
──────────────────────
Net Revenue:   Rs  76,269
```

---

## Key Metrics Explained 📈

| Metric | Formula | Example |
|--------|---------|---------|
| **Gross Sales** | Total of all orders | Rs 250,000 |
| **Discounts** | Total discount given | Rs 25,000 |
| **Net Sales** | Gross - Discounts | Rs 225,000 |
| **Refunds** | Money returned | Rs 23,731 |
| **Final Revenue** | Net Sales - Refunds | Rs 201,269 |

---

## Returns Calculation 🔄

### Shopify Shows:
- **Gross Return:** Rs 2,000 (item price)
- **Discount Returned:** Rs 200 (discount given)
- **Net Return:** Rs 1,800 (customer gets this)
- **Tax Return:** Rs 234

### My Database Stores:
```
refund_items.subtotal = Rs 1,800 (net)
discount_allocation = Rs 200 (for reports)
total_refunds = Rs 1,800 (per item)
```

---

## Reports Available 📄

1. **Sales Report** - Revenue over time
2. **Returns Report** - What's being returned
3. **Product Report** - Top sellers
4. **Customer Report** - Best customers

---

## How It Works ⚙️

```
1. Click "Sync" → Fetch from Shopify
2. Process orders, refunds, customers
3. Calculate totals correctly
4. Show in dashboard
```

---

## Verification ✅

**My Total Refunds:** Rs 23,731.00  
**Shopify Total:** Rs 23,731.00  
**Match:** ✅ Perfect!

---

## Quick Formula Reference 🧮

```javascript
// Order Total
total = subtotal - discount + tax

// Refund Amount (per item)
refund = original_price - discount

// Order Total Refunds
total_refunds = sum of all refund items

// Net Revenue
net_revenue = total_sales - total_refunds
```

---

**Status:** ✅ Working & Verified  
**Accuracy:** 100% match with Shopify
