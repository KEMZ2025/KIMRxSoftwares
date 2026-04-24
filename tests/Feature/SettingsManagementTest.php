<?php

namespace Tests\Feature;

use App\Models\ClientSetting;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_screen_renders_for_admin_context(): void
    {
        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $response = $this->actingAs($user)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Settings');
        $response->assertSee('Pharmacy Profile');
        $response->assertSee('Print Identity');
        $response->assertSee('Cash Drawer Control');
        $response->assertSee('Alert Threshold Amount');
        $response->assertSee('URA / EFRIS Readiness');
        $response->assertSee('EFRIS Sync Queue');
        $response->assertSee('Transport Mode');
        $response->assertSee('EFRIS Sale Submission URL');
    }

    public function test_settings_update_persists_client_branch_and_print_preferences(): void
    {
        [$user, $clientId, $branchId] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $logoFile = UploadedFile::fake()->create('vip-logo.png', 120, 'image/png');

        $response = $this->actingAs($user)->put(route('settings.update'), [
            'client_name' => 'VIP Pharmacy Updated',
            'client_email' => 'vip.updated@example.com',
            'client_phone' => '0700000000',
            'client_address' => 'Kampala Road',
            'client_logo_file' => $logoFile,
            'branch_name' => 'Retail Branch',
            'branch_code' => 'RTL',
            'branch_email' => 'retail@example.com',
            'branch_phone' => '0780000000',
            'branch_address' => 'Plot 10 Kampala',
            'currency_symbol' => 'UGX',
            'tax_label' => 'TIN',
            'tax_number' => '100200300',
            'cash_drawer_alert_threshold' => 350000,
            'efris_environment' => 'sandbox',
            'efris_transport_mode' => 'http',
            'efris_tin' => '101202303',
            'efris_legal_name' => 'VIP Pharmacy Limited',
            'efris_business_name' => 'VIP Pharmacy',
            'efris_branch_code' => 'VIP-MAIN',
            'efris_device_serial' => 'DEVICE-001',
            'efris_auth_url' => 'https://efris-uat.example.com/auth',
            'efris_submission_url' => 'https://efris-uat.example.com/sales',
            'efris_reversal_url' => 'https://efris-uat.example.com/reversals',
            'efris_username' => 'vip-connector',
            'efris_password' => 'secret-pass',
            'efris_client_id' => 'client-app-id',
            'efris_client_secret' => 'client-secret-key',
            'receipt_header' => 'Thank you for shopping with us',
            'receipt_footer' => 'Goods once sold are not returnable.',
            'invoice_footer' => 'Prepared by KIM Rx',
            'report_footer' => 'Confidential branch report',
            'default_line_count' => 5,
            'allow_small_receipt' => '1',
            'allow_large_receipt' => '1',
            'allow_small_invoice' => '1',
            'allow_large_invoice' => '1',
            'allow_small_proforma' => '1',
            'allow_large_proforma' => '0',
            'hide_discount_line_on_print' => '1',
            'show_logo_on_print' => '1',
            'show_branch_contacts_on_print' => '1',
            'allow_add_one_line' => '1',
            'allow_add_five_lines' => '1',
        ]);

        $response->assertRedirect(route('settings.index'));

        $savedLogoPath = DB::table('clients')->where('id', $clientId)->value('logo');

        $this->assertNotNull($savedLogoPath);
        $this->assertStringStartsWith('uploads/client-logos/client-' . $clientId . '/', $savedLogoPath);
        $this->assertTrue(File::exists(public_path($savedLogoPath)));

        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'name' => 'VIP Pharmacy Updated',
            'email' => 'vip.updated@example.com',
            'phone' => '0700000000',
            'address' => 'Kampala Road',
        ]);

        $this->assertDatabaseHas('branches', [
            'id' => $branchId,
            'name' => 'Retail Branch',
            'code' => 'RTL',
            'email' => 'retail@example.com',
            'phone' => '0780000000',
            'address' => 'Plot 10 Kampala',
        ]);

        $this->assertDatabaseHas('client_settings', [
            'client_id' => $clientId,
            'currency_symbol' => 'UGX',
            'tax_label' => 'TIN',
            'tax_number' => '100200300',
            'cash_drawer_alert_threshold' => 350000,
            'efris_environment' => 'sandbox',
            'efris_transport_mode' => 'http',
            'efris_tin' => '101202303',
            'efris_legal_name' => 'VIP Pharmacy Limited',
            'efris_business_name' => 'VIP Pharmacy',
            'efris_branch_code' => 'VIP-MAIN',
            'efris_device_serial' => 'DEVICE-001',
            'efris_auth_url' => 'https://efris-uat.example.com/auth',
            'efris_submission_url' => 'https://efris-uat.example.com/sales',
            'efris_reversal_url' => 'https://efris-uat.example.com/reversals',
            'receipt_header' => 'Thank you for shopping with us',
            'default_line_count' => 5,
            'allow_small_receipt' => true,
            'allow_large_receipt' => true,
            'allow_small_proforma' => true,
            'hide_discount_line_on_print' => true,
            'show_logo_on_print' => true,
            'show_branch_contacts_on_print' => true,
        ]);

        $settings = ClientSetting::query()->where('client_id', $clientId)->firstOrFail();

        $this->assertSame('vip-connector', $settings->efris_username);
        $this->assertSame('secret-pass', $settings->efris_password);
        $this->assertSame('client-app-id', $settings->efris_client_id);
        $this->assertSame('client-secret-key', $settings->efris_client_secret);

        File::delete(public_path($savedLogoPath));
    }

    private function createUserContext(): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'VIP Pharmacy',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ClientSetting::create([
            'client_id' => $clientId,
            'business_mode' => 'both',
            'retail_pos_enabled' => true,
            'wholesale_pos_enabled' => true,
            'purchases_enabled' => true,
            'suppliers_enabled' => true,
            'customers_enabled' => true,
            'inventory_enabled' => true,
            'cash_drawer_enabled' => true,
            'efris_enabled' => true,
            'accounts_enabled' => true,
            'reports_enabled' => true,
            'employees_enabled' => true,
            'proforma_enabled' => true,
            'support_enabled' => true,
            'efris_transport_mode' => 'simulate',
            'show_purchase_price_in_pos' => false,
            'show_last_purchase_price_in_pos' => false,
            'hide_out_of_stock_in_search' => true,
            'allow_small_receipt' => true,
            'allow_small_invoice' => true,
            'allow_large_receipt' => true,
            'allow_large_invoice' => true,
            'allow_small_proforma' => false,
            'allow_large_proforma' => false,
            'hide_discount_line_on_print' => true,
            'currency_symbol' => 'UGX',
            'tax_label' => 'TIN',
            'show_logo_on_print' => true,
            'show_branch_contacts_on_print' => true,
            'default_line_count' => 1,
            'allow_add_one_line' => true,
            'allow_add_five_lines' => true,
        ]);

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }
}
