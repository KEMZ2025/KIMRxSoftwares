<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_elohim_host_shows_elohim_login_branding(): void
    {
        $this->get('https://elohim.kimrxsoftware.com/login')
            ->assertOk()
            ->assertSee('images/elohim-logo.png', false)
            ->assertSee('Elohim Drugshop');

        $this->get('http://localhost/login')
            ->assertOk()
            ->assertDontSee('images/elohim-logo.png', false);
    }

    public function test_elohim_host_rejects_other_client_logins_and_sessions(): void
    {
        $vip = $this->userFor('VIP PHARMACY', 'vip@example.test');
        $elohim = $this->userFor('ELOHIM DRUGSHOP', 'elohim@example.test');

        $this->post('https://elohim.kimrxsoftware.com/login', ['email' => $vip->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($vip)->get('https://elohim.kimrxsoftware.com/dashboard')
            ->assertRedirect('https://elohim.kimrxsoftware.com/login');
        $this->assertGuest();

        $this->post('https://elohim.kimrxsoftware.com/login', ['email' => $elohim->email, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($elohim);
    }

    private function userFor(string $clientName, string $email): User
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName, 'business_mode' => 'retail_only', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId, 'name' => 'Main Branch', 'code' => 'MAIN',
            'is_main' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create([
            'client_id' => $clientId, 'branch_id' => $branchId,
            'email' => $email, 'is_active' => true,
        ]);
    }
}
