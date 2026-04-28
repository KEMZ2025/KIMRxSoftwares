<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceClaimBatch extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RECONCILED = 'reconciled';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'client_id',
        'branch_id',
        'insurer_id',
        'created_by',
        'updated_by',
        'batch_number',
        'title',
        'status',
        'period_start',
        'period_end',
        'submitted_at',
        'reconciled_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_RECONCILED => 'Reconciled',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? 'Unknown';
    }

    public function insurer()
    {
        return $this->belongsTo(Insurer::class);
    }

    public function claims()
    {
        return $this->hasMany(Sale::class, 'insurance_claim_batch_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
