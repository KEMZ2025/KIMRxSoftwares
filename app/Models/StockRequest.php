<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequest extends Model
{
    protected $fillable = [
        'client_id', 'branch_id', 'product_id', 'requested_by_user_id', 'medicine_name',
        'strength', 'dosage_form', 'quantity', 'unit_name', 'note', 'status',
        'request_key', 'submission_token', 'version',
    ];

    protected $casts = ['quantity' => 'decimal:2', 'version' => 'integer'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public static function groupingKey(array $values): string
    {
        $identity = !empty($values['product_id'])
            ? ['product', (string) $values['product_id'], $values['unit_name'] ?? '']
            : ['unlisted', $values['medicine_name'], $values['strength'] ?? '',
                $values['dosage_form'] ?? '', $values['unit_name'] ?? ''];

        return hash('sha256', json_encode(array_map(
            fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value))),
            $identity
        ), JSON_THROW_ON_ERROR));
    }
}
