<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControlBootstrapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateElohimAdmin extends Command
{
    protected $signature = 'client:create-elohim-admin {--execute} {--confirm=}';

    protected $description = 'Create the first Elohim administrator with an interactively entered password';

    public function handle(): int
    {
        $name = 'ELOHIM DRUGSHOP';
        $email = 'admin@elohim.com';
        $client = Client::query()->where('name', $name)->first();
        $branch = $client
            ? Branch::query()->where('client_id', $client->id)->where('is_main', true)->first()
            : null;
        if (!$client || !$branch) {
            $this->error('Onboard Elohim and its Main Branch before creating an admin.');
            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()
            || User::query()->where('client_id', $client->id)->exists()) {
            $this->error('The email or Elohim user account already exists. Nothing was changed.');
            return self::FAILURE;
        }

        $this->line("New Elohim administrator: {$email} / {$branch->name}");
        if (!$this->option('execute')) {
            $this->info('Preview only. No account was created.');
            return self::SUCCESS;
        }

        if ($this->option('confirm') !== $name) {
            $this->error('Pass --confirm with the exact client name to execute.');
            return self::FAILURE;
        }

        if (!$this->input->isInteractive()) {
            $this->error('Run this command interactively so the password is not exposed in arguments or logs.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (at least 12 characters)');
        $confirmation = (string) $this->secret('Confirm new password');
        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Passwords must match and contain at least 12 characters. Nothing was changed.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($client, $branch, $email, $password): void {
            app(AccessControlBootstrapper::class)->ensureForClient($client->id);
            $role = Role::query()->where('client_id', $client->id)->where('name', 'Admin')->sole();
            $user = User::query()->create([
                'name' => 'Elohim Admin',
                'email' => $email,
                'password' => $password,
                'client_id' => $client->id,
                'branch_id' => $branch->id,
                'is_active' => true,
                'is_super_admin' => false,
            ]);
            $user->roles()->attach($role->id);
        });

        $this->info("Elohim administrator {$email} created.");
        return self::SUCCESS;
    }
}
