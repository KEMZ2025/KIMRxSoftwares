<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'company_name',
        'contact_person',
        'phone_primary',
        'phone_secondary',
        'email',
        'whatsapp',
        'website',
        'hours',
        'response_note',
    ];
}
