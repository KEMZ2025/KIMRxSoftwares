<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDrawerDraw extends Model
{
    protected $fillable = [
        'cash_drawer_session_id',
        'client_id',
        'branch_id',
        'amount',
        'reason',
        'drawn_by',
        'drawn_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'drawn_at' => 'datetime',
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

    public function drawnByUser()
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }
}
