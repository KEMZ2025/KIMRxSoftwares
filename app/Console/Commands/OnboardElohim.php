<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientPackagePresetCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OnboardElohim extends Command
{
    protected $signature = 'client:onboard-elohim {--execute} {--confirm=}';

    protected $description = 'Create Elohim as a retail-only Enterprise client without users or imported data';

    public function handle(): int
    {
        $name = 'ELOHIM DRUGSHOP';
        $phone = '0767650635/0708184243';
        $address = 'NAMATABA-MUKONO';

        if (Client::query()->where('name', $name)->exists()) {
            $this->error('Elohim already exists. No client settings were changed.');
            return self::FAILURE;
        }

        $this->line("Client: {$name} (active, paying Enterprise, retail only)");
        $this->line("Main Branch: {$address}; {$phone}");
        $this->line('Subdomain: elohim.kimrxsoftware.com; no admin login or data will be created.');

        if (!$this->option('execute')) {
            $this->info('Preview only. No records were changed.');
            return self::SUCCESS;
        }

        if ($this->option('confirm') !== $name) {
            $this->error('Pass --confirm with the exact client name to execute.');
            return self::FAILURE;
        }

        $preset = ClientPackagePresetCatalog::preset(ClientPackagePresetCatalog::PRESET_ENTERPRISE);
        if (!$preset) {
            $this->error('Enterprise package preset is missing.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $phone, $address, $preset): void {
            $client = Client::query()->create([
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'logo' => 'images/elohim-logo.png',
                'business_mode' => 'retail_only',
                'package_preset' => ClientPackagePresetCatalog::PRESET_ENTERPRISE,
                'client_type' => Client::TYPE_PAYING,
                'subscription_status' => Client::STATUS_ACTIVE,
                'active_user_limit' => $preset['active_user_limit'],
                'is_active' => true,
                'is_platform_sandbox' => false,
            ]);

            Branch::query()->create([
                'client_id' => $client->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'phone' => $phone,
                'address' => $address,
                'business_mode' => 'inherit',
                'is_main' => true,
                'is_active' => true,
            ]);

            ClientSetting::query()->create(['client_id' => $client->id] + array_replace(
                $preset['feature_values'],
                [
                    'business_mode' => 'retail_only',
                    'wholesale_pos_enabled' => false,
                    'insurance_enabled' => false,
                    'efris_enabled' => false,
                    'currency_symbol' => 'UGX',
                    'show_logo_on_print' => true,
                    'show_branch_contacts_on_print' => true,
                    'receipt_footer' => 'Thank you for choosing ELOHIM DRUGSHOP.',
                    'invoice_footer' => 'Thank you for choosing ELOHIM DRUGSHOP.',
                    'report_footer' => 'ELOHIM DRUGSHOP',
                ]
            ));

            app(AccessControlBootstrapper::class)->ensureForClient($client->id);
        });

        $this->info('Elohim client and Main Branch created. No admin user or operational records were added.');
        return self::SUCCESS;
    }
}
