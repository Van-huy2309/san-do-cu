<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\EscrowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_marketplace_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Escrow')
            ->assertSee('Đăng bán')
            ->assertSee('Gợi ý hôm nay');
    }

    public function test_home_filmstrip_shows_popular_ads_without_opening(): void
    {
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-filmstrip', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'iPhone 15 Pro Max filmstrip ad',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 18990000,
            'status' => 'active',
            'views' => 428,
            'city' => 'Hà Nội',
            'areas' => ['Hà Nội'],
            'published_at' => now(),
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Được tìm nhiều', $html);
        $this->assertStringContainsString('Quảng cáo', $html);
        $this->assertStringContainsString('iPhone 15 Pro Max filmstrip ad', $html);

        $this->assertMatchesRegularExpression(
            '/<section[^>]*category-filmstrip-section[\s\S]*?<\/section>/',
            $html
        );
        preg_match('/<section[^>]*category-filmstrip-section[\s\S]*?<\/section>/', $html, $match);
        $chunk = $match[0];

        $this->assertStringContainsString('data-url', $chunk);
        $this->assertStringContainsString('/tin/'.$listing->slug, $chunk);
        $this->assertStringContainsString('Xem tin', $chunk);
    }

    public function test_home_does_not_embed_three_d_paper(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('three-d-paper-section', $html);
        $this->assertStringNotContainsString('threeui/3d-paper', $html);
    }

    public function test_login_splits_form_and_three_d_paper(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('auth-split', $html);
        $this->assertStringContainsString('auth-paper', $html);
        $this->assertStringContainsString('threeui/3d-paper-relic.html', $html);
        $this->assertStringContainsString('mode=login', $html);
        $this->assertStringContainsString('Đăng nhập', $html);
        $this->assertStringContainsString('Email', $html);

        $sourcePath = public_path('threeui/3d-paper-relic.html');
        $this->assertFileExists($sourcePath);
        $source = file_get_contents($sourcePath);
        $this->assertStringContainsString('Xin chào', $source);
        $this->assertStringContainsString('Chào mừng', $source);
        $this->assertStringContainsString('Cảm ơn bạn đã quay lại.', $source);
        $this->assertStringContainsString('Cảm ơn bạn đã đăng ký.', $source);
        $this->assertStringNotContainsString('KYC người bán', $source);
        $this->assertStringContainsString('--bg:#eef7ff', $source);
        $this->assertStringContainsString('#0b3a52', $source);
    }

    public function test_register_paper_uses_welcome_mode(): void
    {
        $html = $this->get(route('register'))->assertOk()->getContent();
        $this->assertStringContainsString('mode=register', $html);
        $this->assertStringContainsString('id="name"', $html);
    }

    public function test_area_filters_listings_and_seller_can_cover_many_regions(): void
    {
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-area', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Sony', 'slug' => 'sony-area']);

        Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'title' => 'Máy Hà Nội only',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 1000000,
            'status' => 'active',
            'city' => 'Hà Nội',
            'areas' => ['Hà Nội'],
            'published_at' => now(),
        ]);
        Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'title' => 'Máy bán nhiều tỉnh',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 2000000,
            'status' => 'active',
            'city' => 'Hồ Chí Minh',
            'areas' => ['Hồ Chí Minh', 'Đà Nẵng'],
            'published_at' => now(),
        ]);

        $this->get(route('home', ['city' => 'Hà Nội']))->assertOk();
        $this->assertSame(1, Listing::public()->inArea('Hà Nội')->count());
        $this->assertTrue(Listing::public()->inArea('Đà Nẵng')->where('title', 'Máy bán nhiều tỉnh')->exists());
        $this->assertFalse(Listing::public()->inArea('Đà Nẵng')->where('title', 'Máy Hà Nội only')->exists());

        $this->get(route('listings.index', ['city' => 'Đà Nẵng']))
            ->assertOk()
            ->assertSee('Máy bán nhiều tỉnh');
    }

    public function test_email_code_verifies_without_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $code = app(\App\Services\EmailVerificationService::class)->issue($user);

        $this->actingAs($user)
            ->post(route('verification.confirm'), ['code' => $code])
            ->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_browse_but_not_buy_or_sell(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('seller.listings.create'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('user.payment.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_seller_create_requires_kyc(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('seller.listings.create'))
            ->assertRedirect(route('account.kyc'));
    }

    public function test_escrow_release_credits_seller_minus_fee(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create(['kyc_status' => 'verified', 'wallet_balance' => 0]);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Apple', 'slug' => 'apple']);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'title' => 'iPhone test',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 1000000,
            'status' => 'sold',
        ]);
        $order = Order::create([
            'code' => 'RLCTEST1',
            'user_id' => $buyer->id,
            'name' => 'Buyer',
            'address' => 'HN',
            'phone' => '0901234567',
            'total_price' => 1000000,
            'status' => 'paid',
            'escrow_status' => 'held',
            'escrow_amount' => 1000000,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'title' => 'iPhone test',
            'quantity' => 1,
            'price' => 1000000,
        ]);

        app(EscrowService::class)->releaseToSellers($order->fresh('items'));

        $this->assertSame(950000, (int) $seller->fresh()->wallet_balance);
        $this->assertSame('released', $order->fresh()->escrow_status);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'commission',
            'amount' => 50000,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'payout',
            'amount' => 950000,
            'status' => 'completed',
        ]);
    }

    public function test_admin_can_remove_listing_lock_and_delete_user_with_orders(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $buyer = User::factory()->create();
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-admin', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Máy admin gỡ',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 10000,
            'status' => 'active',
        ]);
        $order = Order::create([
            'code' => 'RLCADM1',
            'user_id' => $buyer->id,
            'name' => 'Buyer',
            'address' => 'HN',
            'phone' => '0901234567',
            'total_price' => 10000,
            'status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'title' => 'Máy admin gỡ',
            'quantity' => 1,
            'price' => 10000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.listings.destroy', $listing), ['reason' => 'Vi phạm mô tả'])
            ->assertRedirect();
        $this->assertSame('hidden', $listing->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.users.lock', $seller), ['reason' => 'Spam tin ảo'])
            ->assertRedirect();
        $this->assertTrue((bool) $seller->fresh()->is_banned);

        $this->actingAs($admin)
            ->post(route('admin.users.destroy', $seller))
            ->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $seller->id]);
    }

    public function test_admin_can_create_update_and_delete_empty_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Máy nghe nhạc',
                'icon' => '🎵',
                'accent' => '#22d3c5',
                'description' => 'MP3, DAP',
            ])
            ->assertRedirect();

        $category = Category::where('slug', 'may-nghe-nhac')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'DAP & MP3',
                'icon' => '🎧',
                'accent' => '#111111',
                'description' => 'Đã đổi',
                'is_active' => '1',
            ])
            ->assertRedirect();
        $this->assertSame('DAP & MP3', $category->fresh()->name);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_pending_listing_stays_off_market_until_admin_approves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-mod', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Xiaomi Redmi cho duyet Relic',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 15000,
            'status' => 'pending_review',
            'city' => 'Hà Nội',
            'areas' => ['Hà Nội'],
        ]);

        $this->get(route('home'))->assertOk()->assertDontSee('Xiaomi Redmi cho duyet Relic');
        $this->get(route('listings.show', $listing))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('chờ duyệt');

        $this->actingAs($admin)
            ->post(route('admin.listings.approve', $listing))
            ->assertRedirect();
        $this->assertSame('active', $listing->fresh()->status);

        $this->get(route('home'))->assertOk()->assertSee('Xiaomi Redmi cho duyet Relic');
    }

    public function test_admin_cannot_sell_or_use_cart(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'verified']);

        $this->actingAs($admin)
            ->get(route('seller.listings.create'))
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)
            ->get(route('user.cart.index'))
            ->assertRedirect(route('admin.dashboard'));
        $this->assertFalse((new User(['role' => 'admin', 'kyc_status' => 'unverified']))->kycVerified());
    }

    public function test_cod_keeps_order_when_ghn_cannot_create_shipment(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'shipping-order/fee')) {
                return Http::response(['code' => 200, 'data' => ['total' => 22000]], 200);
            }
            if (str_contains($url, 'shipping-order/create')) {
                return Http::response(['code' => 400, 'message' => 'fail'], 200);
            }

            return Http::response(['code' => 200, 'data' => []], 200);
        });

        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $buyer = User::factory()->create();
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-ghn', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Máy GHN fail',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 20000,
            'weight' => 400,
            'status' => 'active',
            'published_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->withSession([
                'cart' => [
                    (string) $listing->id => [
                        'id' => $listing->id,
                        'slug' => $listing->slug,
                        'seller_id' => $seller->id,
                        'name' => $listing->title,
                        'price' => (int) $listing->price,
                        'quantity' => 1,
                        'weight' => 400,
                        'image' => null,
                    ],
                ],
            ])
            ->post(route('user.payment.process'), [
                'name' => 'Nguoi Mua',
                'phone' => '0901234567',
                'address' => '1 Trang Tien',
                'to_district_id' => 1482,
                'to_ward_code' => '1A0606',
                'payment_method' => 'cod',
            ])
            ->assertRedirect(route('user.orders.index'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('cod_ordered', \App\Models\Order::first()->status);
        $this->assertSame('active', $listing->fresh()->status);
    }

    public function test_empty_area_session_is_forgotten(): void
    {
        $this->withSession(['relic.area' => 'Hà Nội'])
            ->get(route('home'))
            ->assertOk();

        $this->assertNull(session('relic.area'));
    }

    public function test_seller_republish_rejected_listing_goes_live(): void
    {
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-rej', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Tin bi tu choi',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 20000,
            'status' => 'rejected',
        ]);

        $this->actingAs($seller)
            ->post(route('seller.listings.publish', $listing))
            ->assertRedirect();
        $this->assertSame('active', $listing->fresh()->status);
        $this->assertNotNull($listing->fresh()->published_at);
    }

    public function test_checkout_charges_the_accepted_offer_not_the_list_price(): void
    {
        Http::fake(fn () => Http::response(['code' => 400, 'message' => 'fail'], 200));

        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $buyer = User::factory()->create();
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-deal', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Máy đã chốt giá',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 1000000,
            'weight' => 400,
            'status' => 'active',
            'published_at' => now(),
        ]);
        \App\Models\Conversation::create([
            'listing_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'accepted_price' => 800000,
            'last_message_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->withSession([
                'cart' => [
                    (string) $listing->id => [
                        'id' => $listing->id,
                        'slug' => $listing->slug,
                        'seller_id' => $seller->id,
                        'name' => $listing->title,
                        'price' => 800000,
                        'quantity' => 1,
                        'weight' => 400,
                        'image' => null,
                    ],
                ],
            ])
            ->post(route('user.payment.process'), [
                'name' => 'Nguoi Mua',
                'phone' => '0901234567',
                'address' => '1 Trang Tien',
                'to_district_id' => 1482,
                'to_ward_code' => '1A0606',
                'payment_method' => 'cod',
            ])
            ->assertRedirect(route('user.orders.index'));

        $item = OrderItem::firstOrFail();
        $this->assertSame(800000, (int) $item->price);
        $this->assertSame(800000, (int) Order::first()->total_price);
    }

    public function test_buyer_cancel_refunds_held_escrow_and_relists(): void
    {
        $seller = User::factory()->create(['kyc_status' => 'verified']);
        $buyer = User::factory()->create(['wallet_balance' => 0]);
        $category = Category::create(['name' => 'Phone', 'slug' => 'phone-refund', 'is_active' => true]);
        $listing = Listing::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'title' => 'Máy đang giữ tiền',
            'description' => str_repeat('máy đẹp ', 8),
            'condition' => 'good',
            'price' => 1000000,
            'status' => 'sold',
            'sold_at' => now(),
        ]);
        $order = Order::create([
            'code' => 'RLCREFUND1',
            'user_id' => $buyer->id,
            'name' => 'Nguoi Mua',
            'address' => '1 Trang Tien',
            'phone' => '0901234567',
            'total_price' => 1000000,
            'status' => 'paid',
            'escrow_status' => 'held',
            'escrow_amount' => 1000000,
            'shipping_status' => 'ready_to_pick',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'title' => $listing->title,
            'quantity' => 1,
            'price' => 1000000,
        ]);

        \App\Models\PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'momo',
            'amount' => 1022000,
            'status' => 'paid',
        ]);

        $this->actingAs($buyer)
            ->post(route('user.orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->escrow_status);
        $this->assertSame(1022000, (int) $buyer->fresh()->wallet_balance);
        $this->assertSame('active', $listing->fresh()->status);
    }
}
