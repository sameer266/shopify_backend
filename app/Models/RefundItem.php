<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundItem extends Model
{
    protected $fillable = [
        'refund_id',
        'order_item_id',
        'shopify_line_item_id',
        'product_id',
        'quantity',
        'subtotal',
        'discount_allocation',
        'total_tax',
        'restock_type',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'  => 'decimal:2',
            'discount_allocation' => 'decimal:2',
            'total_tax' => 'decimal:2',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
