<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingOrigin;
use App\Models\ListingReport;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OtpCode;
use App\Models\PaymentTransaction;
use App\Models\Review;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class WipeDemoDataCommand extends Command
{
    protected $signature = 'relic:wipe-demo';

    protected $description = 'Xóa tin, đơn, shop seed để test Relic từ đầu. Giữ admin và danh mục/thương hiệu.';

    public function handle(): int
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            Message::class,
            Conversation::class,
            Dispute::class,
            PaymentTransaction::class,
            OrderItem::class,
            Order::class,
            Favorite::class,
            Review::class,
            ListingReport::class,
            ListingOrigin::class,
            ListingImage::class,
            Listing::class,
            WalletTransaction::class,
            OtpCode::class,
        ] as $model) {
            if (class_exists($model)) {
                $model::query()->delete();
            }
        }

        User::query()->where('role', '!=', 'admin')->delete();
        if (Schema::hasTable('search_alerts')) {
            DB::table('search_alerts')->delete();
        }
        Category::query()->where('is_active', false)->whereDoesntHave('listings')->delete();
        Cache::forget('relic.categories.active');
        Schema::enableForeignKeyConstraints();

        $dir = public_path('images/listings');
        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $file) {
                File::delete($file->getPathname());
            }
        }

        $this->info('Đã xóa dữ liệu demo. Admin: admin@relic.test / RelicAdmin!234');

        return self::SUCCESS;
    }
}
