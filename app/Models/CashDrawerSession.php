<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashDrawerSession extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'session_date',
        'opening_balance',
        'opening_note',
        'opened_by',
        'day_closed_at',
        'day_closed_by',
        'day_closing_expected_balance',
        'day_closing_counted_balance',
        'day_closing_variance',
        'day_closing_note',
        'day_reopened_at',
        'day_reopened_by',
        'day_reopening_note',
    ];

    protected $casts = [
        'session_date' => 'date',
        'opening_balance' => 'decimal:2',
        'day_closed_at' => 'datetime',
        'day_closing_expected_balance' => 'decimal:2',
        'day_closing_counted_balance' => 'decimal:2',
        'day_closing_variance' => 'decimal:2',
        'day_reopened_at' => 'datetime',
    ];

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

    public function draws()
    {
        return $this->hasMany(CashDrawerDraw::class)->orderByDesc('drawn_at')->orderByDesc('id');
    }

    public function shifts()
    {
        return $this->hasMany(CashDrawerShift::class)->orderByDesc('opened_at')->orderByDesc('id');
    }

    public function dayClosedByUser()
    {
        return $this->belongsTo(User::class, 'day_closed_by');
    }

    public function dayReopenedByUser()
    {
        return $this->belongsTo(User::class, 'day_reopened_by');
    }
}
