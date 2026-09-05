<?php

namespace App\Models;

use App\Support\Accounting\ChartOfAccounts;
use Illuminate\Database\Eloquent\Model;

class AccountingExpense extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'account_code',
        'expense_date',
        'amount',
        'payment_method',
        'payee_name',
        'reference_number',
        'description',
        'notes',
        'entered_by',
        'is_active',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'expense_date' => 'datetime',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function enteredByUser()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function voidedByUser()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function getAccountNameAttribute(): string
    {
        return ChartOfAccounts::account((string) $this->account_code)['name'];
    }
}
