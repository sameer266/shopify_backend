<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fulfillment extends Model
{
    protected $fillable = [
        'order_id',
        'shopify_fulfillment_id',
        'status',
        'tracking_company',
        'tracking_number',
        'created_at_shopify',
        'updated_at_shopify',
    ];

    protected function casts(): array
    {
        return [
            'created_at_shopify' => 'datetime',
            'updated_at_shopify' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
