<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceClaimAdjustment extends Model
{
    public const TYPE_WRITEOFF = 'writeoff';

    protected $fillable = [
        'client_id',
        'branch_id',
        'sale_id',
        'insurer_id',
        'created_by',
        'adjustment_type',
        'amount',
        'adjustment_date',
        'reason',
        'notes',
        'mark_claim_rejected',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'adjustment_date' => 'datetime',
        'mark_claim_rejected' => 'boolean',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_WRITEOFF => 'Write-Off / Shortfall',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->adjustment_type] ?? 'Adjustment';
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function insurer()
    {
        return $this->belongsTo(Insurer::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
