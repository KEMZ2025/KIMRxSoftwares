<?php

namespace App\Http\Controllers;

use App\Support\PlatformSupportSettings;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function __construct(
        protected PlatformSupportSettings $supportSettings,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $resolved = $this->supportSettings->resolved();
        $support = $resolved + [
            'tel_primary' => $this->phoneLink($resolved['phone_primary'] ?? null),
            'tel_secondary' => $this->phoneLink($resolved['phone_secondary'] ?? null),
            'mailto' => $this->mailLink($resolved['email'] ?? null),
            'whatsapp_url' => $this->whatsAppLink($resolved['whatsapp'] ?? null),
        ];

        return view('support.index', [
            'user' => $user,
            'clientName' => $user?->client?->name ?? 'No Client',
            'branchName' => $user?->branch?->name ?? 'No Branch',
            'support' => $support,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function phoneLink(mixed $phone): ?string
    {
        $value = $this->nullableString($phone);

        if ($value === null) {
            return null;
        }

        $sanitized = preg_replace('/[^0-9+]/', '', $value) ?: '';

        return $sanitized !== '' ? 'tel:' . $sanitized : null;
    }

    private function mailLink(mixed $email): ?string
    {
        $value = $this->nullableString($email);

        return $value !== null ? 'mailto:' . $value : null;
    }

    private function whatsAppLink(mixed $phone): ?string
    {
        $value = $this->nullableString($phone);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? 'https://wa.me/' . $digits : null;
    }
}
