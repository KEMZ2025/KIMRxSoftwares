<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class VipBirthdayBannerTest extends TestCase
{
    public function test_message_is_visible_only_for_vip_on_the_birthday_in_uganda(): void
    {
        try {
            Carbon::setTestNow(Carbon::parse('2026-10-07 21:00:00', 'UTC'));
            $vip = new User();
            $vip->setRelation('client', new Client(['name' => 'VIP PHARMACY']));
            $render = fn ($user, $active = true) => view('layouts.vip-birthday-banner', [
                'authUser' => $user, 'tenantWorkspaceActive' => $active,
            ])->render();
            $html = $render($vip);
            $this->assertStringContainsString('Happy Birthday to our wonderful Manager!', $html);
            $this->assertStringContainsString('#mainContent::before', $html);
            $this->assertStringContainsString('2026-10-09T00:00:00+03:00', $html);
            $other = new User();
            $other->setRelation('client', new Client(['name' => 'ELOHIM DRUGSHOP']));
            $this->assertStringNotContainsString('Happy Birthday', $render($other));
            $this->assertStringNotContainsString('Happy Birthday', $render($vip, false));
            Carbon::setTestNow(Carbon::parse('2026-10-08 21:00:00', 'UTC'));
            $this->assertStringNotContainsString('Happy Birthday', $render($vip));
            Carbon::setTestNow(Carbon::parse('2026-10-07 20:59:59', 'UTC'));
            $this->assertStringNotContainsString('Happy Birthday', $render($vip));
        } finally {
            Carbon::setTestNow();
        }
    }
}
