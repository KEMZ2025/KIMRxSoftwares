<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDrawerShift extends Model
{
    protected $fillable = [
        'cash_drawer_session_id',
        'client_id',
        'branch_id',
        'opened_by',
        'opening_balance',
        'opening_note',
        'opened_at',
        'closed_by',
        'closing_expected_balance',
        'closing_counted_balance',
        'closing_variance',
        'banked_amount',
        'handover_amount',
        'closing_note',
        'closed_at',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_note' => 'string',
        'opened_at' => 'datetime',
        'closing_expected_balance' => 'decimal:2',
        'closing_counted_balance' => 'decimal:2',
        'closing_variance' => 'decimal:2',
        'banked_amount' => 'decimal:2',
        'handover_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CashDrawerSession::class, 'cash_drawer_session_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedByUser()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
