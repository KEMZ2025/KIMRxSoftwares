<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseCancellation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['effective_at' => 'datetime', 'amount' => 'decimal:2', 'snapshot' => 'array'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
