<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeoService;
use App\Services\MediaService;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_otp_creates_session_user(): void
    {
        config(['services.sms.driver' => 'log']);
        $code = app(OtpService::class)->send('0901112233');

        $this->postJson('/api/auth/otp/verify', [
            'phone' => '0901112233',
            'code' => $code,
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_api_login_returns_sanctum_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    }

    public function test_api_otp_debug_code_in_log_driver(): void
    {
        config(['services.sms.driver' => 'log']);

        $this->postJson('/api/auth/otp/send', ['phone' => '0901112233'])
            ->assertOk()
            ->assertJsonStructure(['debug_code']);
    }

    public function test_gps_radius_filters_listings(): void
    {
        $this->get(route('listings.index', [
            'lat' => 21.0278,
            'lng' => 105.8342,
            'radius' => 5,
            'sort' => 'nearby',
            'gps' => 1,
        ]))->assertOk();
    }

    public function test_geo_distance_hanoi_to_hcm_is_far(): void
    {
        $km = app(GeoService::class)->distanceKm(21.0278, 105.8342, 10.7769, 106.7009);
        $this->assertTrue($km > 900 && $km < 1300);
    }

    public function test_media_service_stays_on_public_without_s3(): void
    {
        config([
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.key' => null,
        ]);
        $this->assertSame('public', app(MediaService::class)->disk());
        $this->assertStringContainsString('placeholder', app(MediaService::class)->url(null));
    }

    public function test_login_page_is_email_password_only(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('Mật khẩu')
            ->assertSee('Hiện')
            ->assertDontSee('Google')
            ->assertDontSee('Apple')
            ->assertDontSee('OTP');
    }
}
