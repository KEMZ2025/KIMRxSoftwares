<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'expense_date' => 'datetime',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function enteredByUser()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
