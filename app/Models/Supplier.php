<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'client_id',
        'name',
        'contact_person',
        'phone',
        'alt_phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class);
    }
}
