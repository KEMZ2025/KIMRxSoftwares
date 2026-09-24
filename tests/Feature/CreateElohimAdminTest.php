<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateElohimAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_only_after_confirmation_with_a_strong_password(): void
    {
        $this->artisan('client:create-elohim-admin')->assertExitCode(1);
        $this->artisan('client:onboard-elohim', [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->assertExitCode(0);

        $this->artisan('client:create-elohim-admin')->assertExitCode(0);
        $this->artisan('client:create-elohim-admin', ['--execute' => true])->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);

        $this->artisan('client:create-elohim-admin', [
            '--execute' => true, '--confirm' => 'ELOHIM DRUGSHOP',
        ])->expectsQuestion('New password (at least 12 characters)', 'A-longer-test-secret-42')
            ->expectsQuestion('Confirm new password', 'A-longer-test-secret-42')
            ->assertExitCode(0);

        $client = Client::query()->where('name', 'ELOHIM DRUGSHOP')->sole();
        $user = User::query()->where('email', 'admin@elohim.com')->sole();
        $this->assertSame($client->id, $user->client_id);
        $this->assertFalse($user->is_super_admin);
        $this->assertTrue(Hash::check('A-longer-test-secret-42', $user->password));
        $this->assertTrue($user->roles()->where('name', 'Admin')->exists());

        $this->artisan('client:create-elohim-admin')->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }
}
