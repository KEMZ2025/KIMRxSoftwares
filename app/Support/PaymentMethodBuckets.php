<?php

namespace App\Support;

use Illuminate\Support\Str;

class PaymentMethodBuckets
{
    public static function definitions(): array
    {
        return [
            [
                'key' => 'cash',
                'label' => 'Cash',
                'tone' => 'cash',
            ],
            [
                'key' => 'mtn',
                'label' => 'MTN',
                'tone' => 'mtn',
            ],
            [
                'key' => 'airtel',
                'label' => 'Airtel',
                'tone' => 'airtel',
            ],
            [
                'key' => 'bank',
                'label' => 'Bank',
                'tone' => 'bank',
            ],
            [
                'key' => 'cheque',
                'label' => 'Cheque',
                'tone' => 'cheque',
            ],
        ];
    }

    public static function normalize(?string $method): string
    {
        $normalized = Str::of((string) $method)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();

        return match ($normalized) {
            'cash', 'bulky cash', 'petty cash' => 'cash',
            'mtn' => 'mtn',
            'airtel' => 'airtel',
            'bank' => 'bank',
            'cheque', 'check', 'direct', 'other', 'additional methods', 'other unspecified', 'other / unspecified', 'legacy unspecified' => 'cheque',
            default => 'cheque',
        };
    }
}
