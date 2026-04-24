<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItemCorrection extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'purchase_id',
        'purchase_item_id',
        'old_product_id',
        'new_product_id',
        'old_batch_number',
        'new_batch_number',
        'old_expiry_date',
        'new_expiry_date',
        'old_unit_cost',
        'new_unit_cost',
        'old_retail_price',
        'new_retail_price',
        'old_wholesale_price',
        'new_wholesale_price',
        'affected_batch_count',
        'affected_sale_count',
        'affected_sale_item_count',
        'reason',
        'corrected_by',
    ];

    protected $casts = [
        'old_expiry_date' => 'date',
        'new_expiry_date' => 'date',
        'old_unit_cost' => 'decimal:2',
        'new_unit_cost' => 'decimal:2',
        'old_retail_price' => 'decimal:2',
        'new_retail_price' => 'decimal:2',
        'old_wholesale_price' => 'decimal:2',
        'new_wholesale_price' => 'decimal:2',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function oldProduct()
    {
        return $this->belongsTo(Product::class, 'old_product_id');
    }

    public function newProduct()
    {
        return $this->belongsTo(Product::class, 'new_product_id');
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
