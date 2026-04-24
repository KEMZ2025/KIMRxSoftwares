<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        [$user] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertRedirect(route('account.password.edit'));

        $user->refresh();

        $this->assertTrue(Hash::check('new-password123', $user->password));
    }

    public function test_incorrect_current_password_blocks_password_change(): void
    {
        [$user] = $this->createUserContext();

        app(AccessControlBootstrapper::class)->ensureForUser($user);

        $this->from(route('account.password.edit'))
            ->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertRedirect(route('account.password.edit'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_forgot_password_request_sends_reset_notification(): void
    {
        [$user] = $this->createUserContext();

        Notification::fake();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_token_updates_password(): void
    {
        [$user] = $this->createUserContext();

        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'reset-password123',
            'password_confirmation' => 'reset-password123',
        ])->assertRedirect(route('login'));

        $user->refresh();

        $this->assertTrue(Hash::check('reset-password123', $user->password));
    }

    private function createUserContext(): array
    {
        $clientId = $this->createClient('KimRx Password Client');
        $branchId = $this->createBranch($clientId, 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
        ]);

        return [$user, $clientId, $branchId];
    }

    private function createClient(string $name): int
    {
        return DB::table('clients')->insertGetId([
            'name' => $name,
            'business_mode' => 'both',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBranch(int $clientId, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
