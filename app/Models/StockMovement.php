<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'product_id',
        'product_batch_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity_in',
        'quantity_out',
        'balance_after',
        'note',
        'created_by',
    ];

    protected $casts = [
        'quantity_in' => 'decimal:2',
        'quantity_out' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
}