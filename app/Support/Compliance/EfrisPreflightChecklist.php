<?php

namespace App\Support\Compliance;

use App\Models\ClientSetting;
use App\Support\ClientFeatureAccess;

class EfrisPreflightChecklist
{
    public static function build(ClientSetting $settings): array
    {
        $transport = self::transportMode($settings);

        $checks = [
            self::check('Module Enabled', ClientFeatureAccess::efrisEnabled($settings), true, 'Platform owner must enable the URA / EFRIS module for this client.'),
            self::check('Environment', filled($settings->efris_environment), true, 'Choose sandbox/UAT or production.'),
            self::check('Transport Mode', in_array($transport, ['simulate', 'http'], true), true, 'Choose whether this client should stay on simulation or use real HTTP submission.'),
            self::check('URA TIN', filled($settings->efris_tin ?: $settings->tax_number), true, 'Required for fiscal submission.'),
            self::check('Legal Name', filled($settings->efris_legal_name), true, 'Use the official registered business name.'),
            self::check('Trading Name', filled($settings->efris_business_name), true, 'Use the customer-facing pharmacy name.'),
            self::check('EFRIS Branch Code', filled($settings->efris_branch_code), true, 'Required for branch-level fiscal submission.'),
        ];

        if ($transport === 'http') {
            $checks[] = self::check('Sale Submission URL', filled($settings->efris_submission_url), true, 'Required for live/UAT sale submission.');
            $checks[] = self::check('Reversal URL', filled($settings->efris_reversal_url), true, 'Required for approved-sale cancellation reversals.');
            $checks[] = self::check('Auth URL', filled($settings->efris_auth_url), false, 'Optional. Only needed when the UAT/live connector requires a separate token endpoint.');
            $checks[] = self::check('Connector Username', filled($settings->efris_username), false, 'Optional unless your URA/accredited path requires username auth.');
            $checks[] = self::check('Connector Password', filled($settings->efris_password), false, 'Optional unless your URA/accredited path requires password auth.');
            $checks[] = self::check('Client ID', filled($settings->efris_client_id), false, 'Optional unless your connector uses OAuth/client application credentials.');
            $checks[] = self::check('Client Secret', filled($settings->efris_client_secret), false, 'Optional unless your connector uses OAuth/client application credentials.');
        }

        $missingRequired = collect($checks)
            ->filter(fn (array $check) => $check['required'] && !$check['ready'])
            ->pluck('label')
            ->values()
            ->all();

        return [
            'transport' => $transport,
            'checks' => $checks,
            'ready' => $missingRequired === [],
            'missing_required' => $missingRequired,
        ];
    }

    public static function transportMode(ClientSetting $settings): string
    {
        $mode = (string) ($settings->efris_transport_mode ?: config('efris.transport', 'simulate'));

        return in_array($mode, ['simulate', 'http'], true)
            ? $mode
            : 'simulate';
    }

    private static function check(string $label, bool $ready, bool $required, string $help): array
    {
        return [
            'label' => $label,
            'ready' => $ready,
            'required' => $required,
            'help' => $help,
        ];
    }
}
