<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use App\Support\ClientFeatureAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_drawer_actions_are_logged_and_visible_on_audit_screen(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext('Admin', [
            'cash_drawer_enabled' => true,
            'cash_drawer_alert_threshold' => 250,
        ]);

        $this->actingAs($user)->put(route('cash-drawer.opening.update'), [
            'opening_balance' => 120,
            'opening_note' => 'Morning float',
        ])->assertRedirect(route('cash-drawer.index'));

        $this->actingAs($user)->post(route('cash-drawer.draws.store'), [
            'amount' => 20,
            'reason' => 'Bank deposit',
        ])->assertRedirect(route('cash-drawer.index'));

        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'event_key' => 'cash_drawer.opening_updated',
            'module' => 'Cash Drawer',
            'action' => 'Update Opening Balance',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'event_key' => 'cash_drawer.draw_recorded',
            'module' => 'Cash Drawer',
            'action' => 'Record Cash Draw',
        ]);

        $response = $this->actingAs($user)->get(route('admin.audit.index', [
            'module' => 'Cash Drawer',
        ]));

        $response->assertOk();
        $response->assertSee('Audit Trail');
        $response->assertSee('Update Opening Balance');
        $response->assertSee('Record Cash Draw');
        $response->assertSee('Recorded a cash drawer draw.');
    }

    public function test_platform_client_update_is_logged_for_super_admin(): void
    {
        $superAdmin = User::factory()->create([
            'client_id' => null,
            'branch_id' => null,
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        [$client] = $this->createManagedClient('VIP Pharmacy');

        $response = $this->actingAs($superAdmin)->put(route('admin.platform.clients.update', $client), array_merge([
            'name' => 'VIP Pharmacy Plus',
            'email' => 'vip@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala Road',
            'business_mode' => 'both',
            'is_active' => '1',
        ], $this->featurePayload([
            'cash_drawer_enabled' => true,
        ])));

        $response->assertRedirect(route('admin.platform.clients.index'));

        $this->assertDatabaseHas('audit_logs', [
            'event_key' => 'platform.client_updated',
            'module' => 'Platform',
            'action' => 'Update Client',
            'client_id' => $client->id,
        ]);

        $auditResponse = $this->actingAs($superAdmin)->get(route('admin.audit.index', [
            'search' => 'VIP Pharmacy Plus',
        ]));

        $auditResponse->assertOk();
        $auditResponse->assertSee('Update Client');
        $auditResponse->assertSee('VIP Pharmacy Plus');
    }

    public function test_user_without_audit_permission_cannot_open_audit_screen(): void
    {
        [$user] = $this->createUserContext('Cashier', [
            'cash_drawer_enabled' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.audit.index'))
            ->assertForbidden();
    }

    private function createUserContext(string $roleName = 'Admin', array $settingOverrides = []): array
    {
        [$client] = $this->createManagedClient('Audit Client', $settingOverrides);
        $branch = Branch::query()->where('client_id', $client->id)->firstOrFail();

        if ($roleName === 'Admin') {
            $user = User::factory()->create([
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            app(AccessControlBootstrapper::class)->ensureForUser($user);

            return [$user, $client->id, $branch->id];
        }

        $seedAdmin = User::factory()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($seedAdmin);

        $user = User::factory()->create([
            'client_id' => $client->id,
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $role = Role::query()
            ->where('client_id', $client->id)
            ->where('name', $roleName)
            ->firstOrFail();

        $user->roles()->sync([$role->id]);

        return [$user, $client->id, $branch->id];
    }

    private function createManagedClient(string $name, array $settingOverrides = []): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MAIN-' . $clientId,
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClientSetting::query()->create(array_merge(
            [
                'client_id' => $clientId,
                'business_mode' => 'both',
                'currency_symbol' => 'UGX',
                'tax_label' => 'TIN',
            ],
            ClientFeatureAccess::defaultSettingValues(),
            $settingOverrides
        ));

        return [Client::query()->findOrFail($clientId), Branch::query()->findOrFail($branchId)];
    }

    private function featurePayload(array $overrides = []): array
    {
        $payload = [];

        foreach (array_merge(ClientFeatureAccess::defaultSettingValues(), $overrides) as $field => $value) {
            $payload[$field] = $value ? '1' : '0';
        }

        return $payload;
    }
}
