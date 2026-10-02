<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Cache::forget('relic.categories.active');

        User::updateOrCreate(
            ['email' => 'admin@relic.test'],
            [
                'name' => 'Relic Admin',
                'password' => Hash::make('RelicAdmin!234'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_seller' => false,
            ]
        );

        $keep = [
            ['Điện thoại', 'dien-thoai', '📱', 'Smartphone cũ'],
            ['Máy tính bảng', 'may-tinh-bang', '📲', 'iPad, tablet'],
            ['Laptop', 'laptop', '💻', 'Laptop cũ'],
            ['PC & linh kiện', 'pc-linh-kien', '🖥️', 'CPU, RAM, VGA'],
            ['Màn hình', 'man-hinh', '🖥', 'Monitor'],
            ['Âm thanh', 'am-thanh', '🎧', 'Tai nghe, loa'],
            ['Máy ảnh', 'may-anh', '📷', 'Máy ảnh, máy quay'],
            ['Máy chơi game', 'gaming', '🎮', 'Console, tay cầm'],
            ['Đồng hồ', 'dong-ho', '⌚', 'Smartwatch'],
            ['TV', 'tv', '📺', 'Smart TV cũ'],
            ['Phụ kiện', 'phu-kien', '🔌', 'Sạc, cáp, ốp'],
            ['Thiết bị mạng', 'mang', '📡', 'Router, mesh'],
        ];
        $catModels = [];
        foreach ($keep as $i => $c) {
            $catModels[$c[1]] = Category::updateOrCreate(
                ['slug' => $c[1]],
                ['name' => $c[0], 'icon' => $c[2], 'description' => $c[3], 'sort_order' => $i, 'is_active' => true, 'accent' => '#7c5cfc']
            );
        }
        $merge = [
            'tai-nghe' => 'am-thanh',
            'loa' => 'am-thanh',
            'may-quay' => 'may-anh',
            'phu-kien-dt' => 'phu-kien',
            'gia-dung' => 'phu-kien',
            'may-in' => 'pc-linh-kien',
        ];
        foreach (Category::all() as $existing) {
            if (isset($merge[$existing->slug]) && isset($catModels[$merge[$existing->slug]])) {
                Listing::where('category_id', $existing->id)
                    ->update(['category_id' => $catModels[$merge[$existing->slug]]->id]);
                $existing->delete();
            } elseif (! isset($catModels[$existing->slug])) {
                if ($existing->listings()->exists()) {
                    $existing->update(['is_active' => false]);
                } else {
                    $existing->delete();
                }
            }
        }

        foreach ([
            ['Apple', 1.18], ['Samsung', 1.06], ['Sony', 1.08], ['Xiaomi', 0.98],
            ['Dell', 1.02], ['Asus', 1.00], ['Canon', 1.07], ['Nintendo', 1.10],
            ['LG', 1.00], ['JBL', 1.02], ['TP-Link', 0.96],
        ] as $b) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($b[0])],
                ['name' => $b[0], 'price_multiplier' => $b[1]]
            );
        }

        $this->seedDemoMarketplace($catModels);
    }

    /** Dữ liệu mẫu để Care/Ops trả lời từ DB thật (idempotent). */
    private function seedDemoMarketplace(array $catModels): void
    {
        $buyer = User::updateOrCreate(
            ['email' => 'buyer@relic.test'],
            [
                'name' => 'Minh Buyer',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => now(),
                'phone' => '0901111222',
                'is_seller' => false,
                'kyc_status' => 'none',
                'wallet_balance' => 150000,
            ]
        );

        $seller = User::updateOrCreate(
            ['email' => 'seller@relic.test'],
            [
                'name' => 'Lan Shop',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => now(),
                'phone' => '0903333444',
                'is_seller' => true,
                'kyc_status' => 'verified',
                'kyc_full_name' => 'Nguyen Thi Lan',
                'kyc_front_path' => 'kyc/demo-front.jpg',
                'kyc_back_path' => 'kyc/demo-back.jpg',
                'kyc_id_last4' => '5678',
                'wallet_balance' => 2500000,
            ]
        );

        User::updateOrCreate(
            ['email' => 'kycwait@relic.test'],
            [
                'name' => 'Hung KYC Cho',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => now(),
                'phone' => '0905555666',
                'is_seller' => true,
                'kyc_status' => 'pending',
                'kyc_full_name' => 'Tran Van Hung',
                'kyc_front_path' => 'kyc/demo-h-front.jpg',
                'kyc_back_path' => 'kyc/demo-h-back.jpg',
                'kyc_id_last4' => '9012',
            ]
        );

        User::updateOrCreate(
            ['email' => 'locked@relic.test'],
            [
                'name' => 'Nam Bi Khoa',
                'password' => Hash::make('password'),
                'role' => 'user',
                'email_verified_at' => now(),
                'is_seller' => false,
                'is_banned' => true,
                'kyc_status' => 'none',
            ]
        );

        $apple = Brand::where('slug', 'apple')->first();
        $samsung = Brand::where('slug', 'samsung')->first();
        $dell = Brand::where('slug', 'dell')->first();
        $phone = $catModels['dien-thoai'] ?? Category::where('slug', 'dien-thoai')->first();
        $laptop = $catModels['laptop'] ?? Category::where('slug', 'laptop')->first();
        if (! $phone || ! $laptop) {
            return;
        }

        $samples = [
            [
                'slug_key' => 'demo-iphone-13',
                'seller_id' => $seller->id,
                'category_id' => $phone->id,
                'brand_id' => $apple?->id,
                'title' => 'iPhone 13 128GB xanh',
                'description' => 'Máy đẹp pin 88% zin, đủ hộp sạc. Bảo hành shop 30 ngày. Không iCloud lock.',
                'condition' => 'good',
                'original_price' => 15000000,
                'price' => 8900000,
                'status' => 'active',
                'published_at' => now()->subDays(2),
            ],
            [
                'slug_key' => 'demo-samsung-a54',
                'seller_id' => $seller->id,
                'category_id' => $phone->id,
                'brand_id' => $samsung?->id,
                'title' => 'Samsung Galaxy A54 5G',
                'description' => 'Máy zin pin tốt, trầy nhẹ khung. Fullbox. Ship GHN toàn quốc.',
                'condition' => 'excellent',
                'original_price' => 9000000,
                'price' => 5600000,
                'status' => 'active',
                'published_at' => now()->subDay(),
            ],
            [
                'slug_key' => 'demo-dell-xps',
                'seller_id' => $seller->id,
                'category_id' => $laptop->id,
                'brand_id' => $dell?->id,
                'title' => 'Dell XPS 13 i7 16GB',
                'description' => 'Laptop mỏng nhẹ, SSD 512GB, bàn phím đẹp. Pin ~5 tiếng thực tế.',
                'condition' => 'good',
                'original_price' => 28000000,
                'price' => 14500000,
                'status' => 'active',
                'published_at' => now()->subHours(8),
            ],
            [
                'slug_key' => 'demo-pending-phone',
                'seller_id' => $seller->id,
                'category_id' => $phone->id,
                'brand_id' => $apple?->id,
                'title' => 'iPhone 12 chờ duyệt',
                'description' => 'Tin demo chờ kiểm duyệt.',
                'condition' => 'good',
                'original_price' => 12000000,
                'price' => 6200000,
                'status' => 'pending_review',
                'published_at' => null,
            ],
        ];

        foreach ($samples as $row) {
            $key = $row['slug_key'];
            unset($row['slug_key']);
            $existing = Listing::where('slug', 'like', $key.'%')->first();
            if ($existing) {
                $existing->update($row);
            } else {
                Listing::create(array_merge($row, [
                    'slug' => $key.'-'.Str::lower(Str::random(4)),
                    'city' => 'Hà Nội',
                ]));
            }
        }

        if (! \App\Models\Order::where('code', 'RLCDEMO0001')->exists()) {
            $listing = Listing::where('title', 'Samsung Galaxy A54 5G')->first();
            if ($listing) {
                $order = \App\Models\Order::create([
                    'code' => 'RLCDEMO0001',
                    'user_id' => $buyer->id,
                    'name' => $buyer->name,
                    'address' => '12 Nguyen Trai, HN',
                    'phone' => $buyer->phone ?? '0901111222',
                    'total_price' => (int) $listing->price + 30000,
                    'status' => 'paid',
                    'escrow_status' => 'held',
                    'escrow_amount' => (int) $listing->price,
                    'ghn_total_fee' => 30000,
                ]);
                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'listing_id' => $listing->id,
                    'seller_id' => $seller->id,
                    'title' => $listing->title,
                    'quantity' => 1,
                    'price' => (int) $listing->price,
                ]);
            }
        }
    }
}
