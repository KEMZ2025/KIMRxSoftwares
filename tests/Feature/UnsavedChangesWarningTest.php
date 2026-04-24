<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnsavedChangesWarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_create_screen_includes_unsaved_changes_protection_script(): void
    {
        [$user] = $this->createUserContext();
        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('data-kimrx-unsaved-changes-script', false)
            ->assertSee('You have unsaved changes on this screen. Leave without saving?', false)
            ->assertSee('data-unsaved-warning="false"', false);
    }

    private function createUserContext(): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Unsaved Warning Client',
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Main Branch',
            'code' => 'MWC',
            'business_mode' => 'inherit',
            'is_main' => true,
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
