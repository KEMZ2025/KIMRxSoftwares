<?php

namespace App\Http\Controllers;

use App\Models\ClientSetting;
use App\Models\EfrisDocument;
use App\Support\AuditTrail;
use App\Support\ClientFeatureAccess;
use App\Support\Compliance\EfrisPreflightChecklist;
use App\Support\Compliance\EfrisSyncProcessor;
use App\Support\Printing\DocumentBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $client = $user->client;
        $branch = $user->branch;

        $settings = ClientSetting::firstOrCreate(
            ['client_id' => $user->client_id],
            ['business_mode' => $client?->business_mode ?? 'both'] + ClientFeatureAccess::defaultSettingValues()
        );

        $efrisChecklist = EfrisPreflightChecklist::build($settings);

        $efrisSummary = [
            'total' => EfrisDocument::query()->where('client_id', $user->client_id)->count(),
            'ready' => EfrisDocument::query()->where('client_id', $user->client_id)->where('status', 'ready')->count(),
            'submitted' => EfrisDocument::query()->where('client_id', $user->client_id)->where('status', 'submitted')->count(),
            'accepted' => EfrisDocument::query()->where('client_id', $user->client_id)->where('status', 'accepted')->count(),
            'failed' => EfrisDocument::query()->where('client_id', $user->client_id)->where('status', 'failed')->count(),
            'reversal_ready' => EfrisDocument::query()
                ->where('client_id', $user->client_id)
                ->where('next_action', 'submit_reversal')
                ->whereIn('status', ['ready', 'failed'])
                ->count(),
        ];

        $recentEfrisDocuments = EfrisDocument::query()
            ->where('client_id', $user->client_id)
            ->with(['sale:id,invoice_number,receipt_number,status'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return view('settings.index', [
            'user' => $user,
            'client' => $client,
            'branch' => $branch,
            'settings' => $settings,
            'logoPreviewUrl' => DocumentBranding::forUser($user)['logo_url'],
            'efrisChecklist' => $efrisChecklist,
            'efrisSummary' => $efrisSummary,
            'recentEfrisDocuments' => $recentEfrisDocuments,
            'efrisTransport' => $efrisChecklist['transport'],
            'efrisBatchLimit' => max(1, (int) config('efris.batch_limit', 25)),
            'paymentLabels' => [
                'retail_only' => 'Retail Only',
                'wholesale_only' => 'Wholesale Only',
                'both' => 'Retail and Wholesale',
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $client = $user->client;
        $branch = $user->branch;

        abort_unless($client && $branch, 404);

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_address' => ['nullable', 'string', 'max:1000'],
            'client_logo' => ['nullable', 'string', 'max:1000'],
            'client_logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->ignore($branch->id)->where(fn ($query) => $query->where('client_id', $user->client_id)),
            ],
            'branch_email' => ['nullable', 'email', 'max:255'],
            'branch_phone' => ['nullable', 'string', 'max:50'],
            'branch_address' => ['nullable', 'string', 'max:1000'],

            'currency_symbol' => ['required', 'string', 'max:20'],
            'tax_label' => ['required', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'cash_drawer_alert_threshold' => ['nullable', 'numeric', 'min:0'],
            'efris_environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'efris_transport_mode' => ['nullable', Rule::in(['simulate', 'http'])],
            'efris_tin' => ['nullable', 'string', 'max:255'],
            'efris_legal_name' => ['nullable', 'string', 'max:255'],
            'efris_business_name' => ['nullable', 'string', 'max:255'],
            'efris_branch_code' => ['nullable', 'string', 'max:100'],
            'efris_device_serial' => ['nullable', 'string', 'max:100'],
            'efris_auth_url' => ['nullable', 'url', 'max:1000'],
            'efris_submission_url' => ['nullable', 'url', 'max:1000'],
            'efris_reversal_url' => ['nullable', 'url', 'max:1000'],
            'efris_username' => ['nullable', 'string', 'max:255'],
            'efris_password' => ['nullable', 'string', 'max:255'],
            'efris_client_id' => ['nullable', 'string', 'max:255'],
            'efris_client_secret' => ['nullable', 'string', 'max:255'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:2000'],
            'invoice_footer' => ['nullable', 'string', 'max:2000'],
            'report_footer' => ['nullable', 'string', 'max:2000'],
            'default_line_count' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $settings = ClientSetting::firstOrCreate(
            ['client_id' => $user->client_id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );
        $beforeAudit = $this->auditSettingsSnapshot($client, $branch, $settings);

        $logoPath = $client->logo;

        if ($request->hasFile('client_logo_file')) {
            $logoPath = $this->storeClientLogo(
                $request->file('client_logo_file'),
                $client->id,
                $client->logo
            );
        } elseif ($request->boolean('remove_client_logo')) {
            $this->deleteManagedLogo($client->logo);
            $logoPath = null;
        } elseif (!empty($validated['client_logo'])) {
            $manualLogo = trim((string) $validated['client_logo']);

            if ($manualLogo !== $client->logo) {
                $this->deleteManagedLogo($client->logo);
            }

            $logoPath = $manualLogo;
        } elseif (array_key_exists('client_logo', $validated) && trim((string) $validated['client_logo']) === '' && !$client->logo) {
            $logoPath = null;
        }

        DB::transaction(function () use ($client, $branch, $settings, $validated, $request, $logoPath) {
            $client->update([
                'name' => $validated['client_name'],
                'email' => $validated['client_email'] ?: null,
                'phone' => $validated['client_phone'] ?: null,
                'address' => $validated['client_address'] ?: null,
                'logo' => $logoPath ?: null,
            ]);

            $branch->update([
                'name' => $validated['branch_name'],
                'code' => $validated['branch_code'],
                'email' => $validated['branch_email'] ?: null,
                'phone' => $validated['branch_phone'] ?: null,
                'address' => $validated['branch_address'] ?: null,
            ]);

            $settings->update([
                'business_mode' => $client->business_mode,
                'currency_symbol' => strtoupper(trim($validated['currency_symbol'])),
                'tax_label' => trim($validated['tax_label']),
                'tax_number' => $validated['tax_number'] ?: null,
                'cash_drawer_alert_threshold' => array_key_exists('cash_drawer_alert_threshold', $validated)
                    && $validated['cash_drawer_alert_threshold'] !== null
                    && $validated['cash_drawer_alert_threshold'] !== ''
                    ? round((float) $validated['cash_drawer_alert_threshold'], 2)
                    : null,
                'efris_environment' => $validated['efris_environment'] ?? 'sandbox',
                'efris_transport_mode' => $validated['efris_transport_mode'] ?? 'simulate',
                'efris_tin' => $validated['efris_tin'] ?: null,
                'efris_legal_name' => $validated['efris_legal_name'] ?: null,
                'efris_business_name' => $validated['efris_business_name'] ?: null,
                'efris_branch_code' => $validated['efris_branch_code'] ?: null,
                'efris_device_serial' => $validated['efris_device_serial'] ?: null,
                'efris_auth_url' => $validated['efris_auth_url'] ?: null,
                'efris_submission_url' => $validated['efris_submission_url'] ?: null,
                'efris_reversal_url' => $validated['efris_reversal_url'] ?: null,
                'efris_username' => $validated['efris_username'] ?: null,
                'efris_password' => filled($validated['efris_password'] ?? null)
                    ? $validated['efris_password']
                    : $settings->efris_password,
                'efris_client_id' => $validated['efris_client_id'] ?: null,
                'efris_client_secret' => filled($validated['efris_client_secret'] ?? null)
                    ? $validated['efris_client_secret']
                    : $settings->efris_client_secret,
                'receipt_header' => $validated['receipt_header'] ?: null,
                'receipt_footer' => $validated['receipt_footer'] ?: null,
                'invoice_footer' => $validated['invoice_footer'] ?: null,
                'report_footer' => $validated['report_footer'] ?: null,
                'default_line_count' => $validated['default_line_count'],
                'allow_small_receipt' => $request->boolean('allow_small_receipt'),
                'allow_small_invoice' => $request->boolean('allow_small_invoice'),
                'allow_large_receipt' => $request->boolean('allow_large_receipt'),
                'allow_large_invoice' => $request->boolean('allow_large_invoice'),
                'allow_small_proforma' => $request->boolean('allow_small_proforma'),
                'allow_large_proforma' => $request->boolean('allow_large_proforma'),
                'hide_discount_line_on_print' => $request->boolean('hide_discount_line_on_print'),
                'show_logo_on_print' => $request->boolean('show_logo_on_print'),
                'show_branch_contacts_on_print' => $request->boolean('show_branch_contacts_on_print'),
                'allow_add_one_line' => $request->boolean('allow_add_one_line'),
                'allow_add_five_lines' => $request->boolean('allow_add_five_lines'),
            ]);
        });

        $client->refresh();
        $branch->refresh();
        $settings->refresh();

        app(AuditTrail::class)->recordSafely(
            $user,
            'settings.updated',
            'Settings',
            'Update Settings',
            'Updated company, branch, print, and compliance settings.',
            [
                'subject' => $client,
                'subject_label' => $client->name,
                'old_values' => $beforeAudit,
                'new_values' => $this->auditSettingsSnapshot($client, $branch, $settings),
                'context' => [
                    'logo_updated' => $request->hasFile('client_logo_file'),
                    'logo_removed' => $request->boolean('remove_client_logo'),
                    'efris_password_updated' => filled($validated['efris_password'] ?? null),
                    'efris_client_secret_updated' => filled($validated['efris_client_secret'] ?? null),
                ],
            ]
        );

        return redirect()
            ->route('settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    public function processEfris(Request $request, EfrisSyncProcessor $processor)
    {
        $user = $request->user();
        $client = $user->client;

        abort_unless($client, 404);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['ready', 'failed', 'all'])],
        ]);

        $settings = ClientSetting::firstOrCreate(
            ['client_id' => $user->client_id],
            ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
        );

        $hasTrackedDocuments = EfrisDocument::query()
            ->where('client_id', $user->client_id)
            ->exists();

        abort_unless(
            ClientFeatureAccess::efrisEnabled($settings) || $hasTrackedDocuments,
            403
        );

        $summary = $processor->processClient(
            (int) $user->client_id,
            $validated['scope'],
            max(1, (int) config('efris.batch_limit', 25))
        );

        $message = $summary['processed'] === 0
            ? 'No EFRIS documents matched that queue action.'
            : 'EFRIS queue processed ' . $summary['processed'] . ' document(s): '
                . $summary['accepted'] . ' accepted, '
                . $summary['submitted'] . ' submitted, '
                . $summary['failed'] . ' need attention.';

        app(AuditTrail::class)->recordSafely(
            $user,
            'settings.efris_queue_processed',
            'Settings',
            'Process EFRIS Queue',
            'Processed the EFRIS queue for client ' . $client->name . '.',
            [
                'subject' => $client,
                'subject_label' => $client->name,
                'new_values' => [
                    'scope' => $validated['scope'],
                    'processed' => (int) ($summary['processed'] ?? 0),
                    'accepted' => (int) ($summary['accepted'] ?? 0),
                    'submitted' => (int) ($summary['submitted'] ?? 0),
                    'failed' => (int) ($summary['failed'] ?? 0),
                ],
            ]
        );

        return redirect()
            ->route('settings.index')
            ->with('success', $message);
    }

    private function auditSettingsSnapshot($client, $branch, ClientSetting $settings): array
    {
        return [
            'client' => [
                'name' => $client?->name,
                'email' => $client?->email,
                'phone' => $client?->phone,
                'address' => $client?->address,
                'logo' => $client?->logo,
            ],
            'branch' => [
                'name' => $branch?->name,
                'code' => $branch?->code,
                'email' => $branch?->email,
                'phone' => $branch?->phone,
                'address' => $branch?->address,
            ],
            'settings' => [
                'currency_symbol' => $settings->currency_symbol,
                'tax_label' => $settings->tax_label,
                'tax_number' => $settings->tax_number,
                'cash_drawer_alert_threshold' => $settings->cash_drawer_alert_threshold !== null
                    ? round((float) $settings->cash_drawer_alert_threshold, 2)
                    : null,
                'efris_environment' => $settings->efris_environment,
                'efris_transport_mode' => $settings->efris_transport_mode,
                'efris_tin' => $settings->efris_tin,
                'efris_legal_name' => $settings->efris_legal_name,
                'efris_business_name' => $settings->efris_business_name,
                'efris_branch_code' => $settings->efris_branch_code,
                'efris_device_serial' => $settings->efris_device_serial,
                'efris_auth_url' => $settings->efris_auth_url,
                'efris_submission_url' => $settings->efris_submission_url,
                'efris_reversal_url' => $settings->efris_reversal_url,
                'efris_username' => $settings->efris_username,
                'efris_password_set' => filled($settings->efris_password),
                'efris_client_id' => $settings->efris_client_id,
                'efris_client_secret_set' => filled($settings->efris_client_secret),
                'receipt_header' => $settings->receipt_header,
                'receipt_footer' => $settings->receipt_footer,
                'invoice_footer' => $settings->invoice_footer,
                'report_footer' => $settings->report_footer,
                'default_line_count' => (int) $settings->default_line_count,
                'allow_small_receipt' => (bool) $settings->allow_small_receipt,
                'allow_small_invoice' => (bool) $settings->allow_small_invoice,
                'allow_large_receipt' => (bool) $settings->allow_large_receipt,
                'allow_large_invoice' => (bool) $settings->allow_large_invoice,
                'allow_small_proforma' => (bool) $settings->allow_small_proforma,
                'allow_large_proforma' => (bool) $settings->allow_large_proforma,
                'hide_discount_line_on_print' => (bool) $settings->hide_discount_line_on_print,
                'show_logo_on_print' => (bool) $settings->show_logo_on_print,
                'show_branch_contacts_on_print' => (bool) $settings->show_branch_contacts_on_print,
                'allow_add_one_line' => (bool) $settings->allow_add_one_line,
                'allow_add_five_lines' => (bool) $settings->allow_add_five_lines,
            ],
        ];
    }

    private function storeClientLogo($uploadedFile, int $clientId, ?string $existingLogo): string
    {
        $relativeDirectory = 'uploads/client-logos/client-' . $clientId;
        $absoluteDirectory = public_path($relativeDirectory);

        if (!File::exists($absoluteDirectory)) {
            File::makeDirectory($absoluteDirectory, 0755, true);
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension() ?: 'png');
        $filename = 'logo-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(6)) . '.' . $extension;

        $uploadedFile->move($absoluteDirectory, $filename);

        $this->deleteManagedLogo($existingLogo);

        return $relativeDirectory . '/' . $filename;
    }

    private function deleteManagedLogo(?string $logoPath): void
    {
        $logoPath = trim((string) $logoPath);

        if ($logoPath === '' || !str_starts_with($logoPath, 'uploads/client-logos/')) {
            return;
        }

        $absolutePath = public_path(str_replace('/', DIRECTORY_SEPARATOR, $logoPath));

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }
}
