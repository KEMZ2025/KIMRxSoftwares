<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientSetting;
use Illuminate\Support\Facades\DB;

class PlatformSandboxManager
{
    public const CLIENT_NAME = 'Platform Sandbox';
    public const BRANCH_NAME = 'Sandbox Branch';
    public const BRANCH_CODE = 'SBX';

    public function __construct(
        protected AccessControlBootstrapper $bootstrapper,
    ) {
    }

    public function ensure(): array
    {
        return DB::transaction(function () {
            $client = Client::query()->firstOrCreate(
                ['is_platform_sandbox' => true],
                [
                    'name' => self::CLIENT_NAME,
                    'business_mode' => 'both',
                    'client_type' => Client::TYPE_INTERNAL,
                    'subscription_status' => Client::STATUS_ACTIVE,
                    'is_active' => true,
                ]
            );

            if (!$client->wasRecentlyCreated) {
                $client->forceFill([
                    'name' => $client->name ?: self::CLIENT_NAME,
                    'business_mode' => $client->business_mode ?: 'both',
                    'client_type' => Client::TYPE_INTERNAL,
                    'subscription_status' => $client->subscription_status ?: Client::STATUS_ACTIVE,
                    'is_active' => true,
                    'is_platform_sandbox' => true,
                ])->save();
            }

            $branch = Branch::query()->firstOrCreate(
                [
                    'client_id' => $client->id,
                    'code' => self::BRANCH_CODE,
                ],
                [
                    'name' => self::BRANCH_NAME,
                    'business_mode' => 'inherit',
                    'is_main' => true,
                    'is_active' => true,
                ]
            );

            if (!$branch->wasRecentlyCreated) {
                $branch->forceFill([
                    'name' => $branch->name ?: self::BRANCH_NAME,
                    'business_mode' => $branch->business_mode ?: 'inherit',
                    'is_main' => true,
                    'is_active' => true,
                ])->save();
            }

            ClientSetting::query()->firstOrCreate(
                ['client_id' => $client->id],
                ['business_mode' => $client->business_mode] + ClientFeatureAccess::defaultSettingValues()
            );

            $this->bootstrapper->ensureForClient($client->id);

            return [$client->fresh(), $branch->fresh()];
        });
    }

    public function client(): ?Client
    {
        return Client::query()
            ->where('is_platform_sandbox', true)
            ->first();
    }

    public function branch(): ?Branch
    {
        $client = $this->client();

        if (!$client) {
            return null;
        }

        return Branch::query()
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->first();
    }
}
