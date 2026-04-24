<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'ordered_quantity',
        'received_quantity',
        'remaining_quantity',
        'quantity',
        'unit_cost',
        'total_cost',
        'retail_price',
        'wholesale_price',
        'line_status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'ordered_quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function corrections()
    {
        return $this->hasMany(PurchaseItemCorrection::class)->latest();
    }
}
