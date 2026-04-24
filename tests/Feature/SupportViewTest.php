<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupportViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_tenant_user_can_open_support_screen_and_see_contact_details(): void
    {
        config([
            'support.company_name' => 'KIM RETAIL SOFTWARE SYSTEMS',
            'support.phone_primary' => '+256 700 111222',
            'support.phone_secondary' => '+256 701 222333',
            'support.email' => 'support@kimretail.test',
            'support.whatsapp' => '+256 700 111222',
            'support.hours' => 'Daily 8:00 AM - 6:00 PM',
        ]);

        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $response = $this->actingAs($user)->get(route('support.index'));

        $response->assertOk();
        $response->assertSee('KIM RETAIL SOFTWARE SYSTEMS');
        $response->assertSee('+256 700 111222');
        $response->assertSee('support@kimretail.test');
        $response->assertSee('Call Support');
        $response->assertSee('Open WhatsApp');
    }

    public function test_support_screen_requires_authentication(): void
    {
        $response = $this->get(route('support.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_support_screen_prefers_platform_owner_saved_contacts_over_config_defaults(): void
    {
        config([
            'support.company_name' => 'Fallback Support',
            'support.phone_primary' => '+256 799 000000',
            'support.email' => 'fallback@kimretail.test',
        ]);

        PlatformSetting::query()->create([
            'company_name' => 'KIM RETAIL SOFTWARE SYSTEMS',
            'phone_primary' => '+256 700 111222',
            'email' => 'support@kimretail.test',
            'whatsapp' => '+256 700 111222',
        ]);

        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $response = $this->actingAs($user)->get(route('support.index'));

        $response->assertOk();
        $response->assertSee('KIM RETAIL SOFTWARE SYSTEMS');
        $response->assertSee('+256 700 111222');
        $response->assertSee('support@kimretail.test');
        $response->assertDontSee('fallback@kimretail.test');
    }

    private function createUserContext(): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Support Client',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Support Branch',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }
}
