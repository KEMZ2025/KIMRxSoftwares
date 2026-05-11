<?php

namespace App\Support\Printing;

use App\Models\ClientSetting;
use App\Models\User;

class DocumentBranding
{
    private static array $resolvedForUsers = [];

    public static function forUser(User $user): array
    {
        $cacheKey = implode(':', [
            (string) ($user->id ?? 'guest'),
            (string) ($user->client_id ?? 'no-client'),
            (string) ($user->branch_id ?? 'no-branch'),
        ]);

        if (array_key_exists($cacheKey, self::$resolvedForUsers)) {
            return self::$resolvedForUsers[$cacheKey];
        }

        $user->loadMissing([
            'client:id,name,email,phone,address,logo,business_mode',
            'branch:id,name,code,email,phone,address',
        ]);

        $client = $user->client;
        $branch = $user->branch;

        $settings = ClientSetting::query()->firstOrCreate(
            ['client_id' => $user->client_id],
            ['business_mode' => $client?->business_mode ?? 'both']
        );

        $companyEmail = self::cleanPlaceholderContact($client?->email);
        $companyPhone = self::cleanPlaceholderContact($client?->phone);
        $companyAddress = self::cleanPlaceholderAddress($client?->address, [$client?->email, $client?->phone]);
        $branchEmail = self::cleanPlaceholderContact($branch?->email);
        $branchPhone = self::cleanPlaceholderContact($branch?->phone);
        $branchAddress = self::cleanPlaceholderAddress($branch?->address, [$branch?->email, $branch?->phone]);

        return self::$resolvedForUsers[$cacheKey] = [
            'client' => $client,
            'branch' => $branch,
            'settings' => $settings,
            'company_name' => $client?->name ?? 'KIM Rx',
            'company_email' => $companyEmail,
            'company_phone' => $companyPhone,
            'company_address' => $companyAddress,
            'branch_name' => $branch?->name,
            'branch_code' => $branch?->code,
            'branch_email' => $branchEmail,
            'branch_phone' => $branchPhone,
            'branch_address' => $branchAddress,
            'currency_symbol' => $settings->currency_symbol ?: '',
            'tax_label' => $settings->tax_label ?: 'TIN',
            'tax_number' => $settings->tax_number,
            'receipt_header' => $settings->receipt_header,
            'receipt_footer' => $settings->receipt_footer,
            'invoice_footer' => $settings->invoice_footer,
            'invoice_payment_details' => $settings->invoice_payment_details,
            'report_footer' => $settings->report_footer,
            'show_logo' => (bool) $settings->show_logo_on_print,
            'show_branch_contacts' => (bool) $settings->show_branch_contacts_on_print,
            'logo_url' => self::resolveLogoUrl($client?->logo),
            'logo_file' => self::resolveLogoFile($client?->logo),
        ];
    }

    private static function cleanPlaceholderContact(?string $value): ?string
    {
        $value = trim((string) $value);

        return self::isPlaceholderContact($value) ? null : ($value === '' ? null : $value);
    }

    private static function cleanPlaceholderAddress(?string $value, array $relatedContacts): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $isOldDemoAddress = strtolower($value) === 'kampala';
        $hasOldDemoContact = collect($relatedContacts)->contains(
            fn ($contact) => self::isPlaceholderContact($contact)
        );

        return $isOldDemoAddress && $hasOldDemoContact ? null : $value;
    }

    private static function isPlaceholderContact(?string $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, [
            'vip@example.com',
            'main@vip.com',
            '0700000000',
        ], true);
    }
    private static function resolveLogoUrl(?string $logoPath): ?string
    {
        $logoPath = trim((string) $logoPath);

        if ($logoPath === '') {
            return null;
        }

        if (
            str_starts_with($logoPath, 'http://') ||
            str_starts_with($logoPath, 'https://') ||
            str_starts_with($logoPath, 'data:')
        ) {
            return $logoPath;
        }

        return asset(ltrim(str_replace('\\', '/', $logoPath), '/'));
    }

    private static function resolveLogoFile(?string $logoPath): ?string
    {
        $logoPath = trim((string) $logoPath);

        if (
            $logoPath === '' ||
            str_starts_with($logoPath, 'http://') ||
            str_starts_with($logoPath, 'https://') ||
            str_starts_with($logoPath, 'data:')
        ) {
            return null;
        }

        $candidate = public_path(ltrim(str_replace('\\', DIRECTORY_SEPARATOR, $logoPath), DIRECTORY_SEPARATOR));

        return is_file($candidate) ? $candidate : null;
    }
}
