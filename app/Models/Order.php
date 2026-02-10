<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'shopify_order_id',
        'order_number',
        'customer_id',
        'email',
        'financial_status',
        'fulfillment_status',
        'shipping_status',
        'is_paid',
        'total_price',
        'subtotal_price',
        'total_tax',
        'currency',
        'processed_at',
        'closed_at',
        'cancelled_at',
        'cancel_reason',
        'shipping_address',
        'billing_address',
        'note',
      
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'total_price' => 'decimal:2',
            'subtotal_price' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'processed_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'shipping_address' => 'array',
            'billing_address' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->full_name ?? $this->email ?? 'Guest';
    }
}
