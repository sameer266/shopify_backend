<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'shopify_customer_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'addresses',
        'verified_email',
        'state',
        'tags',
        'note',
        'shopify_created_at',
    ];

    protected function casts(): array
    {
        return [
            'addresses' => 'array',
            'verified_email' => 'boolean',
            'shopify_created_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->email ?? 'Guest';
    }
}
