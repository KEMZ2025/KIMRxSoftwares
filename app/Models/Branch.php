<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'business_mode',
        'is_main',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public static function businessModeLabels(): array
    {
        return [
            'inherit' => 'Inherit Client Mode',
            'retail_only' => 'Retail Only',
            'wholesale_only' => 'Wholesale Only',
            'both' => 'Retail and Wholesale',
        ];
    }

    public function effectiveBusinessMode(): string
    {
        return $this->business_mode === 'inherit'
            ? ($this->client?->business_mode ?? 'both')
            : $this->business_mode;
    }

    public function effectiveBusinessModeLabel(): string
    {
        return self::businessModeLabels()[$this->effectiveBusinessMode()] ?? 'Retail and Wholesale';
    }

    public function configuredBusinessModeLabel(): string
    {
        return self::businessModeLabels()[$this->business_mode] ?? 'Inherit Client Mode';
    }
}
