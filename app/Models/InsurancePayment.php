<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsurancePayment extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'sale_id',
        'insurer_id',
        'received_by',
        'reversal_of_payment_id',
        'payment_method',
        'amount',
        'reference_number',
        'payment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function insurer()
    {
        return $this->belongsTo(Insurer::class);
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function originalPayment()
    {
        return $this->belongsTo(self::class, 'reversal_of_payment_id');
    }

    public function reversals()
    {
        return $this->hasMany(self::class, 'reversal_of_payment_id');
    }

    public function getIsReversalAttribute(): bool
    {
        return $this->reversal_of_payment_id !== null;
    }

    public function getReversedAmountAttribute(): float
    {
        if ($this->relationLoaded('reversals')) {
            return (float) $this->reversals->sum('amount');
        }

        return (float) $this->reversals()->sum('amount');
    }

    public function getAvailableToReverseAttribute(): float
    {
        if ($this->reversal_of_payment_id) {
            return 0.0;
        }

        return max(0, (float) $this->amount - (float) $this->reversed_amount);
    }

    public function getDisplayAmountAttribute(): float
    {
        return $this->is_reversal ? (float) $this->amount * -1 : (float) $this->amount;
    }

    public function getEntryTypeLabelAttribute(): string
    {
        return $this->is_reversal ? 'Reversal' : 'Remittance';
    }
}
