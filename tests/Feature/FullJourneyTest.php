<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FullJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_buyer_admin_journey_from_register_to_order(): void
    {
        Mail::fake();
        Notification::fake();
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'test-payment.momo.vn') || str_contains($url, 'gateway/api/create')) {
                return Http::response([
                    'resultCode' => 0,
                    'message' => 'Successful.',
                    'payUrl' => 'https://test-payment.momo.vn/v2/gateway/pay/TESTPAY',
                ], 200);
            }
            if (str_contains($url, 'shipping-order/fee')) {
                return Http::response(['code' => 200, 'data' => ['total' => 22000]], 200);
            }
            if (str_contains($url, 'shipping-order/create')) {
                return Http::response(['code' => 200, 'data' => ['order_code' => 'GHNTEST01']], 200);
            }

            return Http::response(['code' => 200, 'data' => []], 200);
        });

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $brand = Brand::create(['name' => 'Apple', 'slug' => 'apple', 'price_multiplier' => 1]);

        $this->post(route('register'), [
            'name' => 'Nguoi Ban',
            'email' => 'seller@journey.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ])->assertRedirect(route('verification.notice'));

        $seller = User::where('email', 'seller@journey.test')->firstOrFail();
        $sellerCode = app(\App\Services\EmailVerificationService::class)->issue($seller);
        $seller->refresh();
        $this->actingAs($seller)->post(route('verification.confirm'), [
            'code' => $sellerCode,
        ])->assertRedirect(route('home'));
        $seller->refresh();

        $this->actingAs($seller)
            ->get(route('seller.listings.create'))
            ->assertRedirect(route('account.kyc'));

        $this->actingAs($seller)->post(route('account.kyc.store'), [
            'kyc_full_name' => 'Nguyen Van Ban',
            'id_number' => '001234567890',
            'kyc_front' => $this->jpeg('front.jpg'),
            'kyc_back' => $this->jpeg('back.jpg'),
        ])->assertRedirect();
        $this->assertSame('pending', $seller->fresh()->kyc_status);

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@journey.test']);
        $this->actingAs($admin)
            ->post(route('admin.kyc.approve', $seller))
            ->assertRedirect();
        $this->assertSame('verified', $seller->fresh()->kyc_status);

        $this->actingAs($admin)
            ->get(route('user.cart.index'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($seller->fresh())
            ->post(route('seller.listings.store'), [
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'title' => 'iPhone 12 64GB test journey',
                'description' => str_repeat('Máy zin pin tốt. ', 4),
                'model' => 'iPhone 12',
                'color' => 'Black',
                'storage_gb' => 64,
                'year_released' => 2020,
                'condition' => 'good',
                'original_price' => 15000000,
                'price' => 8500000,
                'weight' => 350,
                'areas' => ['Hà Nội'],
                'serial' => 'SN12345678',
                'purchase_channel' => 'Apple Store',
                'purchase_date' => '2021-01-01',
                'images' => [$this->jpeg('phone.jpg')],
            ])
            ->assertRedirect(route('seller.listings.index'));

        $listing = Listing::where('title', 'iPhone 12 64GB test journey')->firstOrFail();
        $this->assertSame('active', $listing->status);
        $this->assertNotNull($listing->published_at);

        $this->actingAs($admin)
            ->get(route('seller.listings.create'))
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('iPhone 12 64GB test journey');

        $this->post(route('logout'));

        $this->post(route('register'), [
            'name' => 'Nguoi Mua',
            'email' => 'buyer@journey.test',
            'password' => 'password12',
            'password_confirmation' => 'password12',
        ]);
        $buyer = User::where('email', 'buyer@journey.test')->firstOrFail();
        $buyerCode = app(\App\Services\EmailVerificationService::class)->issue($buyer);
        $buyer->refresh();
        $this->actingAs($buyer)->post(route('verification.confirm'), [
            'code' => $buyerCode,
        ]);
        $buyer->refresh();

        $this->actingAs($buyer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('iPhone 12 64GB test journey');

        $this->actingAs($buyer)
            ->post(route('user.cart.add', $listing), ['buy_now' => 1])
            ->assertRedirect(route('user.payment.index'));

        $this->actingAs($buyer)
            ->post(route('user.payment.process'), [
                'name' => 'Nguoi Mua',
                'phone' => '0901234567',
                'address' => '1 Trang Tien, Hoan Kiem',
                'to_district_id' => 1482,
                'to_ward_code' => '1A0606',
                'payment_method' => 'momo',
            ])
            ->assertRedirect();

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, $order->paymentTransactions()->count());

        $this->actingAs($buyer)
            ->get(route('user.orders.momo.start', $order))
            ->assertRedirect('https://test-payment.momo.vn/v2/gateway/pay/TESTPAY');

        $this->assertSame(1, $order->fresh()->paymentTransactions()->count());

        $tx = $order->fresh()->paymentTransactions()->latest('id')->firstOrFail();
        $this->assertSame('initiated', $tx->status);
        $this->assertSame((int) $order->total_price, (int) $tx->amount);

        $this->get(route('user.payment.momo.callback', $this->signedMomoPayload($order, $tx)))
            ->assertRedirect(route('user.orders.index'))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('held', $order->escrow_status);
        $this->assertSame(8500000, (int) $order->escrow_amount);
        $this->assertSame('GHNTEST01', $order->ghn_order_code);
        $this->assertSame('active', $listing->fresh()->status);
        $this->assertSame(0, (int) $seller->fresh()->wallet_balance);

        $this->actingAs($buyer)
            ->post(route('user.orders.receive', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('released', $order->escrow_status);

        $gross = 8500000;
        $fee = (int) round($gross * WalletService::COMMISSION_RATE);
        $net = $gross - $fee;
        $this->assertSame(425000, $fee);
        $this->assertSame(8075000, (int) $seller->fresh()->wallet_balance);
        $this->assertTrue(WalletTransaction::query()->where([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'payout',
            'amount' => $net,
            'status' => 'completed',
        ])->exists());
        $this->assertTrue(WalletTransaction::query()->where([
            'user_id' => $seller->id,
            'order_id' => $order->id,
            'type' => 'commission',
            'amount' => $fee,
            'status' => 'completed',
        ])->exists());

        $this->actingAs($buyer)
            ->post(route('reviews.store', $listing), [
                'rating' => 5,
                'comment' => 'Máy đúng mô tả, shop hỗ trợ tốt.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee($order->code);
        $this->actingAs($admin)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('seller@journey.test')
            ->assertSee('buyer@journey.test');
        $this->actingAs($admin)
            ->get(route('admin.finance'))
            ->assertOk()
            ->assertSee('425.000')
            ->assertSee('Phí sàn 5%');
        $this->actingAs($admin)
            ->get(route('admin.listings'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.categories'))
            ->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.disputes'))
            ->assertOk();

        $withdraw = WalletTransaction::query()->where('type', 'withdraw')->where('status', 'pending')->firstOrFail();
        $this->actingAs($admin)
            ->post(route('admin.finance.approve', $withdraw))
            ->assertRedirect();
        $this->assertSame(0, (int) $seller->fresh()->wallet_frozen);
        $this->assertSame(8025000, (int) $seller->fresh()->wallet_balance);

        $this->actingAs($admin)
            ->post(route('admin.listings.destroy', $listing), ['reason' => 'Tin test xong'])
            ->assertRedirect();
        $this->assertSame('hidden', $listing->fresh()->status);
    }

    private function signedMomoPayload(Order $order, PaymentTransaction $tx): array
    {
        $accessKey = (string) config('services.momo.access_key');
        $secretKey = (string) config('services.momo.secret_key');
        $payload = [
            'partnerCode' => (string) config('services.momo.partner_code'),
            'orderId' => $tx->gateway_order_id,
            'requestId' => 'req-test',
            'amount' => (string) (int) $tx->amount,
            'orderInfo' => 'Thanh toan don hang #'.$order->id,
            'orderType' => 'momo_wallet',
            'transId' => '99001122',
            'resultCode' => '0',
            'message' => 'Successful.',
            'payType' => 'napas',
            'responseTime' => '14567890',
            'extraData' => (string) $order->id,
        ];

        $rawHash = 'accessKey='.$accessKey
            .'&amount='.$payload['amount']
            .'&extraData='.$payload['extraData']
            .'&message='.$payload['message']
            .'&orderId='.$payload['orderId']
            .'&orderInfo='.$payload['orderInfo']
            .'&orderType='.$payload['orderType']
            .'&partnerCode='.$payload['partnerCode']
            .'&payType='.$payload['payType']
            .'&requestId='.$payload['requestId']
            .'&responseTime='.$payload['responseTime']
            .'&resultCode='.$payload['resultCode']
            .'&transId='.$payload['transId'];

        $payload['signature'] = hash_hmac('sha256', $rawHash, $secretKey);

        return $payload;
    }

    private function jpeg(string $name): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        ));

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }
}
