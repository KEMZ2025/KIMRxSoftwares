<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insurer extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'credit_days',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'credit_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(InsurancePayment::class);
    }
}
