<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardElohimTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_retail_enterprise_workspace_without_users_or_data(): void
    {
        $this->artisan('client:onboard-elohim')->assertExitCode(0);
        $this->artisan('client:onboard-elohim', ['--execute' => true])->assertExitCode(1);
        $this->assertDatabaseCount('clients', 0);

        $this->artisan('client:onboard-elohim', [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(0);

        $client = Client::query()->where('name', 'ELOHIM DRUGSHOP')->sole();
        $branch = Branch::query()->where('client_id', $client->id)->sole();
        $settings = ClientSetting::query()->where('client_id', $client->id)->sole();
        $this->assertSame('retail_only', $client->business_mode);
        $this->assertSame('enterprise', $client->package_preset);
        $this->assertSame('paying', $client->client_type);
        $this->assertSame('images/elohim-logo.png', $client->logo);
        $this->assertSame('Main Branch', $branch->name);
        $this->assertSame('NAMATABA-MUKONO', $branch->address);
        $this->assertSame('0767650635/0708184243', $branch->phone);
        $this->assertTrue($settings->retail_pos_enabled);
        $this->assertFalse($settings->wholesale_pos_enabled);
        $this->assertTrue($settings->accounts_enabled);
        $this->assertTrue($settings->reports_enabled);
        $this->assertTrue($settings->accounting_general_ledger_enabled);
        foreach (['users', 'customers', 'products', 'suppliers', 'sales', 'purchases'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->artisan('client:onboard-elohim', [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(1);
        $this->assertDatabaseCount('clients', 1);
    }
}
