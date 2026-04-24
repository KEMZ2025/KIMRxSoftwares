<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EfrisDocument extends Model
{
    protected $fillable = [
        'client_id',
        'branch_id',
        'sale_id',
        'environment',
        'document_kind',
        'status',
        'next_action',
        'reference_number',
        'attempt_count',
        'prepared_at',
        'last_attempt_at',
        'submitted_at',
        'accepted_at',
        'reversal_required_at',
        'last_error_message',
        'payload_snapshot',
        'response_snapshot',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'prepared_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'reversal_required_at' => 'datetime',
        'payload_snapshot' => 'array',
        'response_snapshot' => 'array',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function statusLabel(): string
    {
        if ($this->next_action === 'submit_reversal') {
            return match ($this->status) {
                'accepted' => 'Reversal Accepted',
                'submitted' => 'Reversal Submitted',
                'failed' => 'Reversal Failed',
                default => 'Reversal Ready',
            };
        }

        return match ($this->status) {
            'accepted' => 'EFRIS Accepted',
            'submitted' => 'EFRIS Submitted',
            'failed' => 'EFRIS Failed',
            default => 'EFRIS Ready',
        };
    }
}
