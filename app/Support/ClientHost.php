<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ClientHost
{
    public static function expectedClientName(Request $request): ?string
    {
        return config('client_hosts')[strtolower($request->getHost())] ?? null;
    }

    public static function allowsUser(Request $request, ?string $clientName, bool $isSuperAdmin = false): bool
    {
        $expected = self::expectedClientName($request);

        return $expected === null || (!$isSuperAdmin && strcasecmp(trim((string) $clientName), $expected) === 0);
    }
}
