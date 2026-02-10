<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $fillable = [
        'shopify_refund_id',
        'order_id',
        'processed_at',
        'note',
        'gateway',
        'total_amount',
        'total_tax',
        'transactions',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'total_tax'    => 'decimal:2',
            'transactions' => 'array', // Store raw JSON of transactions if needed
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
