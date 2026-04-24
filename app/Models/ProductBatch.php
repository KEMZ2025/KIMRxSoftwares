<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'product_id',
        'purchase_item_id',
        'supplier_id',
        'batch_number',
        'expiry_date',
        'purchase_price',
        'retail_price',
        'wholesale_price',
        'quantity_received',
        'quantity_available',
        'reserved_quantity',
        'is_active',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_price' => 'decimal:2',
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'quantity_available' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'product_batch_id')->latest('adjustment_date');
    }
}
