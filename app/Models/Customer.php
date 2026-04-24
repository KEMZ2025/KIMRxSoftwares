<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'contact_person',
        'phone',
        'alt_phone',
        'email',
        'address',
        'credit_limit',
        'outstanding_balance',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function getRemainingCreditAttribute(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->outstanding_balance);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
