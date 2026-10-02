<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountLockedMail;
use App\Mail\ListingRemovedMail;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Listing;
use App\Models\ListingOrigin;
use App\Models\ListingReport;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\AccountRemovalService;
use App\Services\AdminAnalyticsService;
use App\Services\EscrowService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard(AdminAnalyticsService $analytics)
    {
        $stats = [
            'người dùng' => User::count(),
            'đang bán' => Listing::where('status', 'active')->count(),
            'chờ duyệt' => Listing::where('status', 'pending_review')->count(),
            'hồ sơ nguồn gốc' => ListingOrigin::where('status', 'pending')->count(),
            'đơn hàng' => Order::count(),
            'báo cáo' => ListingReport::where('status', 'open')->count(),
            'KYC' => User::where('kyc_status', 'pending')->count(),
            'khiếu nại' => Dispute::where('status', 'open')->count(),
            'ví chờ' => WalletTransaction::where('status', 'pending')->count(),
        ];
        $latestOrders = Order::with('user')->latest()->take(8)->get();
        $pendingListings = Listing::with('seller')->where('status', 'pending_review')->latest()->take(8)->get();
        $summary = $analytics->summary();
        $series = $analytics->dailySeries(30);
        $ordersByStatus = $analytics->ordersByStatus();
        $listingsByStatus = $analytics->listingsByStatus();

        return view('admin.dashboard', compact(
            'stats', 'latestOrders', 'pendingListings', 'summary', 'series', 'ordersByStatus', 'listingsByStatus'
        ));
    }

    public function listings(Request $request, AdminAnalyticsService $analytics)
    {
        $query = Listing::with(['seller', 'category', 'origin'])
            ->orderByRaw("CASE status WHEN 'pending_review' THEN 0 WHEN 'active' THEN 1 WHEN 'hidden' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        $listings = $query->paginate(20)->withQueryString();
        $chartMap = $analytics->listingsByStatus();
        $sectionStats = [
            'đang bán' => (string) Listing::where('status', 'active')->count(),
            'chờ duyệt' => (string) Listing::where('status', 'pending_review')->count(),
            'đã ẩn' => (string) Listing::where('status', 'hidden')->count(),
            'từ chối' => (string) Listing::where('status', 'rejected')->count(),
        ];
        $chartTitle = 'Tin theo trạng thái';
        $chartId = 'listingsStatusChart';

        return view('admin.listings.index', compact('listings', 'sectionStats', 'chartMap', 'chartTitle', 'chartId'));
    }

    public function approveListing(Listing $listing)
    {
        $listing->update([
            'status' => 'active',
            'published_at' => $listing->published_at ?? now(),
        ]);
        Cache::forget('relic.categories.active');
        \App\Models\SearchAlert::query()->chunkById(100, function ($alerts) use ($listing) {
            foreach ($alerts as $alert) {
                if ($alert->matchesListing($listing)) {
                    $alert->increment('hits');
                    $alert->update(['last_hit_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Đã duyệt tin. Tin đã lên chợ: '.route('listings.show', $listing));
    }

    public function rejectListing(Request $request, Listing $listing)
    {
        $listing->update(['status' => 'rejected']);
        $listing->origin?->update([
            'status' => 'rejected',
            'admin_note' => $request->input('note', 'Hồ sơ chưa đạt.'),
        ]);

        return back()->with('success', 'Đã từ chối tin.');
    }

    public function hideListing(Listing $listing)
    {
        $listing->update(['status' => 'hidden']);

        return back()->with('success', 'Đã ẩn tin.');
    }

    public function verifyOrigin(Listing $listing)
    {
        abort_unless($listing->origin, 404);
        $listing->origin->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => Auth::id(),
        ]);
        if ($listing->status === 'pending_review') {
            $listing->update(['status' => 'active', 'published_at' => now()]);
        }

        return back()->with('success', 'Đã gắn Relic Seal cho tin này.');
    }

    public function users(AdminAnalyticsService $analytics)
    {
        $users = User::latest()->paginate(20);
        $chartMap = $analytics->usersByKyc();
        $sectionStats = [
            'tổng user' => (string) User::count(),
            'mới 30 ngày' => (string) User::where('created_at', '>=', now()->subDays(30))->count(),
            'đang khóa' => (string) User::where('is_banned', true)->count(),
            'KYC chờ' => (string) User::where('kyc_status', 'pending')->count(),
        ];
        $chartTitle = 'User theo KYC';
        $chartId = 'usersKycChart';

        return view('admin.users.index', compact('users', 'sectionStats', 'chartMap', 'chartTitle', 'chartId'));
    }

    public function destroyListing(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);

        $seller = $listing->seller;
        $title = $listing->title;

        if ($listing->orderItems()->exists()) {
            $listing->update(['status' => 'hidden']);
        } else {
            $listing->delete();
        }

        try {
            if ($seller?->email) {
                Mail::to($seller->email)->send(new ListingRemovedMail($seller->name, $title, $data['reason']));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Listing removed mail failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã gỡ tin.');
    }

    public function lockUser(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 403);
        $data = $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ]);
        $user->update([
            'is_banned' => true,
            'ban_reason' => $data['reason'],
            'banned_at' => now(),
        ]);
        try {
            Mail::to($user->email)->send(new AccountLockedMail($user->name, $data['reason'], true));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Lock mail failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã khóa tài khoản.');
    }

    public function unlockUser(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $user->update([
            'is_banned' => false,
            'ban_reason' => null,
            'banned_at' => null,
        ]);
        try {
            Mail::to($user->email)->send(new AccountLockedMail($user->name, 'Tài khoản được mở lại.', false));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Unlock mail failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã mở khóa tài khoản.');
    }

    public function destroyUser(User $user, AccountRemovalService $removal)
    {
        abort_if($user->isAdmin(), 403);
        $removal->delete($user);

        return back()->with('success', 'Đã xóa tài khoản.');
    }

    public function toggleSeller(User $user)
    {
        abort_if($user->isAdmin(), 403);
        $user->update([
            'seller_verified' => ! $user->seller_verified,
            'is_seller' => true,
        ]);

        return back()->with('success', 'Đã cập nhật huy hiệu người bán.');
    }

    public function reports(AdminAnalyticsService $analytics)
    {
        $reports = ListingReport::with(['user', 'listing'])->latest()->paginate(20);
        $chartMap = $analytics->reportsByStatus();
        $sectionStats = [
            'đang mở' => (string) ListingReport::where('status', 'open')->count(),
            'đã đóng' => (string) ListingReport::where('status', 'closed')->count(),
            'tổng' => (string) ListingReport::count(),
        ];
        $chartTitle = 'Báo cáo tin theo trạng thái';
        $chartId = 'reportsStatusChart';

        return view('admin.reports.index', compact('reports', 'sectionStats', 'chartMap', 'chartTitle', 'chartId'));
    }

    public function closeReport(ListingReport $report)
    {
        $report->update(['status' => 'closed']);

        return back()->with('success', 'Đã đóng báo cáo.');
    }

    public function categories()
    {
        $categories = Category::withCount('listings')->orderByDesc('is_active')->orderBy('sort_order')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $this->validatedCategory($request);
        Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueCategorySlug($data['name']),
            'icon' => $data['icon'] ?: '📦',
            'accent' => $data['accent'] ?: '#7c5cfc',
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);
        Cache::forget('relic.categories.active');

        return back()->with('success', 'Đã thêm danh mục.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $this->validatedCategory($request);
        $category->update([
            'name' => $data['name'],
            'slug' => $this->uniqueCategorySlug($data['name'], $category->id),
            'icon' => $data['icon'] ?: '📦',
            'accent' => $data['accent'] ?: '#7c5cfc',
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        Cache::forget('relic.categories.active');

        return back()->with('success', 'Đã cập nhật danh mục.');
    }

    public function destroyCategory(Category $category)
    {
        if ($category->listings()->exists()) {
            $category->update(['is_active' => false]);
            Cache::forget('relic.categories.active');

            return back()->with('error', 'Danh mục đang có tin đăng nên không xóa được. Đã ẩn khỏi sàn.');
        }

        $category->delete();
        Cache::forget('relic.categories.active');

        return back()->with('success', 'Đã xóa danh mục.');
    }

    private function validatedCategory(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:80',
            'icon' => 'nullable|string|max:8',
            'accent' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);
    }

    private function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'danh-muc';
        $slug = $base;
        $i = 1;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function kyc(AdminAnalyticsService $analytics)
    {
        $users = User::whereIn('kyc_status', ['pending', 'verified', 'rejected'])
            ->latest('updated_at')
            ->paginate(20);
        $chartMap = $analytics->usersByKyc();
        $sectionStats = [
            'chờ duyệt' => (string) User::where('kyc_status', 'pending')->count(),
            'đã duyệt' => (string) User::where('kyc_status', 'verified')->count(),
            'từ chối' => (string) User::where('kyc_status', 'rejected')->count(),
        ];
        $chartTitle = 'Phân bố KYC';
        $chartId = 'kycStatusChart';

        return view('admin.kyc.index', compact('users', 'sectionStats', 'chartMap', 'chartTitle', 'chartId'));
    }

    public function approveKyc(User $user)
    {
        $user->update([
            'kyc_status' => 'verified',
            'kyc_reviewed_at' => now(),
            'kyc_note' => null,
            'is_seller' => true,
        ]);

        return back()->with('success', 'Đã duyệt KYC.');
    }

    public function rejectKyc(Request $request, User $user)
    {
        $data = $request->validate(['note' => 'required|string|max:300']);
        $user->update([
            'kyc_status' => 'rejected',
            'kyc_reviewed_at' => now(),
            'kyc_note' => $data['note'],
        ]);

        return back()->with('success', 'Đã từ chối KYC.');
    }

    public function finance(AdminAnalyticsService $analytics)
    {
        $held = (int) Order::where('escrow_status', 'held')->sum('escrow_amount');
        $released = (int) Order::where('escrow_status', 'released')->sum('escrow_amount');
        $commission = (int) WalletTransaction::where('type', 'commission')->where('status', 'completed')->sum('amount');
        $boost = (int) WalletTransaction::where('type', 'boost')->where('status', 'completed')->sum('amount');
        $pendingTx = WalletTransaction::with('user')->where('status', 'pending')->latest()->paginate(20);
        $series = $analytics->dailySeries(30);
        $summary = $analytics->summary();

        return view('admin.finance.wallet', compact(
            'held', 'released', 'commission', 'boost', 'pendingTx', 'series', 'summary'
        ));
    }

    public function approveWallet(WalletTransaction $transaction, WalletService $wallet)
    {
        try {
            if ($transaction->type === 'topup') {
                $wallet->approveTopup($transaction);
            } elseif ($transaction->type === 'withdraw') {
                $wallet->approveWithdraw($transaction);
            } else {
                abort(403);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đối soát giao dịch ví.');
    }

    public function disputes(AdminAnalyticsService $analytics)
    {
        $disputes = Dispute::with(['order.user', 'user'])->latest()->paginate(20);
        $chartMap = $analytics->disputesByStatus();
        $sectionStats = [
            'đang mở' => (string) Dispute::where('status', 'open')->count(),
            'đã xử lý' => (string) Dispute::where('status', '!=', 'open')->count(),
            'tổng' => (string) Dispute::count(),
        ];
        $chartTitle = 'Khiếu nại theo trạng thái';
        $chartId = 'disputesStatusChart';

        return view('admin.disputes.index', compact('disputes', 'sectionStats', 'chartMap', 'chartTitle', 'chartId'));
    }

    public function resolveDispute(Request $request, Dispute $dispute, EscrowService $escrow)
    {
        abort_unless($dispute->status === 'open', 403);
        $data = $request->validate([
            'resolution' => 'required|in:release,refund',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $order = $dispute->order()->with('items', 'user')->firstOrFail();
        try {
            if ($data['resolution'] === 'release') {
                $escrow->releaseToSellers($order, 'Admin giải ngân sau khiếu nại');
            } else {
                $escrow->refundBuyer($order, 'Admin hoàn tiền khiếu nại');
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $dispute->update([
            'status' => 'resolved',
            'resolution' => $data['resolution'],
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Đã xử lý khiếu nại.');
    }
}
