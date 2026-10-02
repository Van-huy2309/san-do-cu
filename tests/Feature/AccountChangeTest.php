<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountChangeTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create([
            'name' => 'Nguoi Mua',
            'phone' => '0901234567',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
    }

    private function pending(string $field, string $value, string $code = '123456'): array
    {
        return ['relic.pending_change' => [
            'field' => $field,
            'value' => $value,
            'code' => $code,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]];
    }

    public function test_start_stores_pending_change_without_touching_the_account(): void
    {
        Mail::fake();
        $user = $this->buyer();

        $response = $this->actingAs($user)->post(route('account.change.start', 'name'), ['name' => 'Ten Moi']);
        $response->assertRedirect(route('account.change.confirm'));

        $pending = session('relic.pending_change');
        $this->assertSame('name', $pending['field']);
        $this->assertSame('Ten Moi', $pending['value']);
        $this->assertSame('Nguoi Mua', $user->fresh()->name);
    }

    public function test_apply_needs_the_right_code_and_password(): void
    {
        $user = $this->buyer();
        $session = $this->pending('name', 'Ten Moi');

        $this->actingAs($user)->withSession($session)
            ->post(route('account.change.apply'), ['code' => '000000', 'current_password' => 'password'])
            ->assertSessionHas('error');
        $this->assertSame('Nguoi Mua', $user->fresh()->name);

        $this->actingAs($user)->withSession($session)
            ->post(route('account.change.apply'), ['code' => '123456', 'current_password' => 'sai-mat-khau'])
            ->assertSessionHas('error');
        $this->assertSame('Nguoi Mua', $user->fresh()->name);

        $this->actingAs($user)->withSession($session)
            ->post(route('account.change.apply'), ['code' => '123456', 'current_password' => 'password'])
            ->assertRedirect(route('account.profile'))
            ->assertSessionMissing('relic.pending_change');
        $this->assertSame('Ten Moi', $user->fresh()->name);
    }

    public function test_visiting_profile_cancels_a_pending_change(): void
    {
        $user = $this->buyer();

        $this->actingAs($user)->withSession($this->pending('phone', '0988777666'))
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSessionMissing('relic.pending_change');
        $this->assertSame('0901234567', $user->fresh()->phone);

        $this->actingAs($user)->get(route('account.change.confirm'))
            ->assertRedirect(route('account.change.index'));
    }

    public function test_expired_pending_change_is_dropped(): void
    {
        $user = $this->buyer();
        $stale = $this->pending('name', 'Ten Moi');
        $stale['relic.pending_change']['expires_at'] = now()->subMinute()->timestamp;

        $this->actingAs($user)->withSession($stale)
            ->post(route('account.change.apply'), ['code' => '123456', 'current_password' => 'password'])
            ->assertRedirect(route('account.change.index'));
        $this->assertSame('Nguoi Mua', $user->fresh()->name);
    }

    public function test_password_change_checks_old_password_before_sending_code(): void
    {
        Mail::fake();
        $user = $this->buyer();

        $this->actingAs($user)->from(route('account.change.form', 'password'))
            ->post(route('account.change.start', 'password'), [
                'current_password' => 'sai-mat-khau',
                'password' => 'MatKhauMoi!234',
                'password_confirmation' => 'MatKhauMoi!234',
            ])
            ->assertRedirect(route('account.change.form', 'password'))
            ->assertSessionHas('error')
            ->assertSessionMissing('relic.pending_change');
    }

    public function test_password_change_applies_after_confirmation(): void
    {
        $user = $this->buyer();

        $this->actingAs($user)->withSession($this->pending('password', Hash::make('MatKhauMoi!234')))
            ->post(route('account.change.apply'), ['code' => '123456', 'current_password' => 'password'])
            ->assertRedirect(route('account.profile'));

        $this->assertTrue(Hash::check('MatKhauMoi!234', $user->fresh()->password));
    }
}
