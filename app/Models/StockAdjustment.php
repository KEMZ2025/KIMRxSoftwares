<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'product_id',
        'product_batch_id',
        'purchase_id',
        'direction',
        'reason',
        'quantity',
        'quantity_received_before',
        'quantity_received_after',
        'quantity_available_before',
        'quantity_available_after',
        'reserved_quantity_before',
        'reserved_quantity_after',
        'note',
        'adjusted_by',
        'adjustment_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_received_before' => 'decimal:2',
        'quantity_received_after' => 'decimal:2',
        'quantity_available_before' => 'decimal:2',
        'quantity_available_after' => 'decimal:2',
        'reserved_quantity_before' => 'decimal:2',
        'reserved_quantity_after' => 'decimal:2',
        'adjustment_date' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function adjustedByUser()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function getDirectionLabelAttribute(): string
    {
        return ucfirst((string) $this->direction);
    }

    public function getReasonLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->reason));
    }
}
