<?php

namespace App\Support;

final class EnhancedPharmacyExperience
{
    public static function enabledFor(?string $clientName): bool
    {
        return in_array(strtoupper(trim((string) $clientName)), [
            'VIP PHARMACY',
            'ELOHIM DRUGSHOP',
        ], true);
    }
}
