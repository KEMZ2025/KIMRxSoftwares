<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'category_id',
        'unit_id',
        'name',
        'strength',
        'barcode',
        'description',
        'purchase_price',
        'retail_price',
        'wholesale_price',
        'track_batch',
        'track_expiry',
        'expiry_alert_days',
        'dispensing_price_guide',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'track_batch' => 'boolean',
        'track_expiry' => 'boolean',
        'expiry_alert_days' => 'integer',
        'dispensing_price_guide' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function normalizedDispensingPriceGuide(): array
    {
        return collect($this->dispensing_price_guide ?? [])
            ->map(function ($line) {
                $quantity = is_array($line) ? (float) ($line['quantity'] ?? 0) : 0;
                $label = is_array($line) ? trim((string) ($line['label'] ?? '')) : '';
                $amount = is_array($line) ? (float) ($line['amount'] ?? 0) : 0;

                if ($quantity <= 0 || $label === '' || $amount < 0) {
                    return null;
                }

                return [
                    'quantity' => $quantity,
                    'label' => $label,
                    'amount' => round($amount, 2),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
