<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    public const SOURCE_LIVE = 'live';
    public const SOURCE_OPENING_BALANCE_IMPORT = 'opening_balance_import';

    protected $fillable = [
        'client_id',
        'branch_id',
        'supplier_id',
        'invoice_number',
        'source',
        'purchase_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_type',
        'payment_status',
        'due_date',
        'invoice_status',
        'notes',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder->whereNull('source')
                ->orWhere('source', self::SOURCE_LIVE);
        });
    }

    public function isOpeningBalanceImport(): bool
    {
        return $this->source === self::SOURCE_OPENING_BALANCE_IMPORT;
    }

    public function isOperational(): bool
    {
        return !$this->isOpeningBalanceImport();
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
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

    public function corrections()
    {
        return $this->hasMany(PurchaseItemCorrection::class)->latest();
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class)->latest('payment_date');
    }
}
