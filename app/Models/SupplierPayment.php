<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'supplier_id',
        'purchase_id',
        'paid_by',
        'payment_method',
        'amount',
        'reference_number',
        'payment_date',
        'status',
        'source',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            'invoice_entry' => 'Invoice Entry',
            'manual' => 'Payment',
            default => ucwords(str_replace('_', ' ', (string) $this->source)),
        };
    }
}
