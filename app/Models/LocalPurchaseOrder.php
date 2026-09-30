<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalPurchaseOrder extends Model
{
    protected $fillable = [
        'client_id', 'branch_id', 'supplier_id', 'supplier_name', 'supplier_phone',
        'supplier_address', 'order_number', 'submission_token', 'order_date',
        'expected_delivery_date', 'delivery_address', 'notes', 'status',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'created_by', 'issued_by', 'cancelled_by', 'issued_at', 'cancelled_at',
        'cancellation_reason', 'version',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    public function items()
    {
        return $this->hasMany(LocalPurchaseOrderItem::class)->orderBy('line_number');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuedByUser()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
