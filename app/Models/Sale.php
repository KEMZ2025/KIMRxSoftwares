<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public const SOURCE_LIVE = 'live';
    public const SOURCE_OPENING_BALANCE_IMPORT = 'opening_balance_import';
    public const CLAIM_DRAFT = 'draft';
    public const CLAIM_SUBMITTED = 'submitted';
    public const CLAIM_APPROVED = 'approved';
    public const CLAIM_REJECTED = 'rejected';
    public const CLAIM_PART_PAID = 'part_paid';
    public const CLAIM_PAID = 'paid';
    public const CLAIM_RECONCILED = 'reconciled';

    protected $fillable = [
        'client_id',
        'branch_id',
        'customer_id',
        'insurer_id',
        'insurance_claim_batch_id',
        'served_by',
        'approved_by',
        'cancelled_by',
        'restored_by',
        'invoice_number',
        'receipt_number',
        'source',
        'sale_type',
        'status',
        'payment_type',
        'payment_method',
        'insurance_plan_name',
        'insurance_member_number',
        'insurance_card_number',
        'insurance_authorization_number',
        'insurance_claim_status',
        'insurance_status_notes',
        'insurance_rejection_reason',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'amount_paid',
        'amount_received',
        'insurance_covered_amount',
        'patient_copay_amount',
        'insurance_balance_due',
        'upfront_amount_paid',
        'balance_due',
        'sale_date',
        'approved_at',
        'insurance_submitted_at',
        'insurance_approved_at',
        'insurance_rejected_at',
        'insurance_paid_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_from_status',
        'restored_at',
        'restore_reason',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'approved_at' => 'datetime',
        'insurance_submitted_at' => 'datetime',
        'insurance_approved_at' => 'datetime',
        'insurance_rejected_at' => 'datetime',
        'insurance_paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'restored_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'insurance_covered_amount' => 'decimal:2',
        'patient_copay_amount' => 'decimal:2',
        'insurance_balance_due' => 'decimal:2',
        'upfront_amount_paid' => 'decimal:2',
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

    public function isInsuranceSale(): bool
    {
        return strtolower((string) $this->payment_type) === 'insurance' && $this->insurer_id !== null;
    }

    public static function insuranceClaimStatusOptions(): array
    {
        return [
            self::CLAIM_DRAFT => 'Draft',
            self::CLAIM_SUBMITTED => 'Submitted',
            self::CLAIM_APPROVED => 'Approved',
            self::CLAIM_REJECTED => 'Rejected',
            self::CLAIM_PART_PAID => 'Part Paid',
            self::CLAIM_PAID => 'Paid',
            self::CLAIM_RECONCILED => 'Reconciled',
        ];
    }

    public function getClaimStatusLabelAttribute(): string
    {
        return self::insuranceClaimStatusOptions()[$this->insurance_claim_status] ?? 'Not Started';
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function insurer()
    {
        return $this->belongsTo(Insurer::class);
    }

    public function insuranceClaimBatch()
    {
        return $this->belongsTo(InsuranceClaimBatch::class, 'insurance_claim_batch_id');
    }

    public function servedByUser()
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function restoredByUser()
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function insurancePayments()
    {
        return $this->hasMany(InsurancePayment::class);
    }

    public function insuranceClaimAdjustments()
    {
        return $this->hasMany(InsuranceClaimAdjustment::class);
    }

    public function efrisDocument()
    {
        return $this->hasOne(EfrisDocument::class);
    }
}
