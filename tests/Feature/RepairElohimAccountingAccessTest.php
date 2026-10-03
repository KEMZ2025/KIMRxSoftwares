<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\ClientFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairElohimAccountingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_is_preview_first_and_restores_only_elohim_accounting_access(): void
    {
        $this->artisan('client:onboard-elohim', ['--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP'])
            ->assertExitCode(0);
        $client = Client::query()->where('name', 'ELOHIM DRUGSHOP')->sole();
        $settings = ClientSetting::query()->where('client_id', $client->id)->sole();
        $settings->forceFill(['accounts_enabled' => false, 'accounting_chart_enabled' => false])->save();
        $role = Role::query()->where('client_id', $client->id)->where('name', 'Admin')->sole();
        $role->permissions()->detach(Permission::query()->where('permission_key', 'accounting.chart')->sole()->id);
        $admin = User::factory()->create([
            'client_id' => $client->id,
            'branch_id' => $client->branches()->firstOrFail()->id,
            'email' => 'admin@elohim.com',
        ]);

        $this->artisan('client:repair-elohim-accounting-access')->assertExitCode(0);
        $this->assertFalse($settings->fresh()->accounts_enabled);
        $this->assertFalse($role->permissions()->where('permission_key', 'accounting.chart')->exists());
        $this->assertFalse($admin->roles()->whereKey($role->id)->exists());

        $this->artisan('client:repair-elohim-accounting-access', ['--execute' => true])->assertExitCode(1);
        $this->assertFalse($settings->fresh()->accounts_enabled);

        $args = ['--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP'];
        $this->artisan('client:repair-elohim-accounting-access', $args)->assertExitCode(0);
        $this->artisan('client:repair-elohim-accounting-access', $args)->assertExitCode(0);

        $this->assertTrue($admin->roles()->whereKey($role->id)->exists());
        $this->assertTrue($role->permissions()->where('permission_key', 'accounting.chart')->exists());
        $settings->refresh();
        $this->assertTrue($settings->accounts_enabled);
        foreach (ClientFeatureAccess::accountingFeatureDefinitions() as $feature) {
            $this->assertTrue($settings->{$feature['field']}, $feature['field']);
        }
        $this->assertTrue($admin->fresh()->hasPermission('accounting.chart'));
        $this->actingAs($admin->fresh())->get(route('accounting.index'))
            ->assertOk()->assertSee('Chart Of Accounts')->assertSee('Fixed Assets');
        $this->get(route('accounting.chart'))->assertOk();
    }
}
