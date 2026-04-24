<?php

namespace App\Models;

use App\Support\Accounting\ChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'asset_name',
        'asset_category',
        'asset_code',
        'acquisition_date',
        'acquisition_cost',
        'salvage_value',
        'useful_life_months',
        'payment_method',
        'vendor_name',
        'reference_number',
        'notes',
        'entered_by',
        'is_active',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'useful_life_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function enteredByUser()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function categoryDefinition(): array
    {
        return ChartOfAccounts::fixedAssetDefinitions()[$this->asset_category] ?? ChartOfAccounts::defaultFixedAssetDefinition();
    }

    public function assetAccountCode(): string
    {
        return $this->categoryDefinition()['asset_account_code'];
    }

    public function accumulatedDepreciationAccountCode(): string
    {
        return $this->categoryDefinition()['accumulated_depreciation_account_code'];
    }

    public function monthlyDepreciationAmount(): float
    {
        $life = max(0, (int) $this->useful_life_months);
        $depreciableBase = max(0, (float) $this->acquisition_cost - (float) $this->salvage_value);

        if ($life === 0 || $depreciableBase <= 0) {
            return 0.0;
        }

        return round($depreciableBase / $life, 2);
    }

    public function depreciationMonthsElapsed(?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? Carbon::today(config('app.timezone')))->copy()->startOfMonth();
        $acquiredAt = Carbon::parse($this->acquisition_date, config('app.timezone'))->startOfMonth();

        if ($asOf->lte($acquiredAt)) {
            return 0;
        }

        return min(
            max(0, $acquiredAt->diffInMonths($asOf)),
            max(0, (int) $this->useful_life_months)
        );
    }

    public function accumulatedDepreciation(?Carbon $asOf = null): float
    {
        return round($this->monthlyDepreciationAmount() * $this->depreciationMonthsElapsed($asOf), 2);
    }

    public function netBookValue(?Carbon $asOf = null): float
    {
        return round(
            max((float) $this->salvage_value, (float) $this->acquisition_cost - $this->accumulatedDepreciation($asOf)),
            2
        );
    }
}
