<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SearchAlert;
use App\Services\AreaService;
use App\Services\GeoService;
use App\Services\MediaService;
use App\Services\OriginService;
use App\Services\PricingEngine;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SellerListingController extends Controller
{
    public function index()
    {
        $listings = Listing::where('seller_id', Auth::id())
            ->with(['images', 'origin', 'category'])
            ->latest()
            ->paginate(12);

        $sales = Auth::user()->listings()->where('status', 'sold')->count();

        return view('seller.listings.index', compact('listings', 'sales'));
    }

    public function create()
    {
        if (Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Tài khoản quản trị không đăng bán trên sàn.');
        }
        if (! Auth::user()->kycVerified()) {
            return redirect()->route('account.kyc')
                ->with('warning', 'Cần xác minh CCCD (KYC) trước khi đăng bán.');
        }

        return view('seller.listings.form', [
            'listing' => new Listing(['condition' => 'good', 'city' => Auth::user()->city]),
            'categories' => Category::activeCached(),
            'brands' => Brand::orderBy('name')->get(),
            'areas' => AreaService::names(),
        ]);
    }

    public function store(Request $request, OriginService $origin, PricingEngine $pricing, MediaService $media)
    {
        abort_if($request->user()->isAdmin(), 403, 'Tài khoản quản trị không đăng bán.');
        abort_unless($request->user()->kycVerified(), 403, 'Cần KYC để đăng tin.');

        $data = $this->validated($request);
        $user = $request->user();
        $user->update(['is_seller' => true]);
        $areas = array_values(array_unique($data['areas']));
        $city = $areas[0];
        $geo = app(GeoService::class)->forCity($city);

        $listing = Listing::create([
            'seller_id' => $user->id,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
            'storage_gb' => $data['storage_gb'] ?? null,
            'year_released' => $data['year_released'] ?? null,
            'condition' => $data['condition'],
            'original_price' => $data['original_price'] ?? $data['price'],
            'price' => $data['price'],
            'weight' => $data['weight'] ?? 400,
            'city' => $city,
            'areas' => $areas,
            'lat' => $data['lat'] ?? $user->lat ?? $geo['lat'],
            'lng' => $data['lng'] ?? $user->lng ?? $geo['lng'],
            'extras' => $this->extrasFrom($data),
            'status' => 'active',
            'published_at' => now(),
        ]);

        $listing->estimated_price = ! empty($data['original_price'])
            ? $pricing->forListing($listing->load('brand'))
            : (int) $data['price'];
        $listing->save();

        $this->storeImages($request, $listing, $media);
        $origin->upsert($listing, $data, $this->optionalFile($request, 'invoice', $media, 'origins'), $this->optionalFile($request, 'box_photo', $media, 'origins'));

        Cache::forget('relic.categories.active');
        $this->pingAlerts($listing);

        return redirect()->route('seller.listings.index')
            ->with('success', 'Tin đã lên chợ. Người mua xem và nhắn shop được ngay.');
    }

    public function edit(Listing $listing)
    {
        $this->authorizeSeller($listing);
        $listing->load(['images', 'origin']);

        return view('seller.listings.form', [
            'listing' => $listing,
            'categories' => Category::activeCached(),
            'brands' => Brand::orderBy('name')->get(),
            'areas' => AreaService::names(),
        ]);
    }

    public function update(Request $request, Listing $listing, OriginService $origin, PricingEngine $pricing, MediaService $media)
    {
        $this->authorizeSeller($listing);
        abort_if($listing->status === 'sold', 403, 'Tin đã bán không sửa được.');

        $data = $this->validated($request);
        $areas = array_values(array_unique($data['areas']));
        $city = $areas[0];
        $geo = app(GeoService::class)->forCity($city);
        $status = in_array($listing->status, ['rejected', 'pending_review'], true) ? 'active' : $listing->status;
        $listing->update([
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'model' => $data['model'] ?? null,
            'color' => $data['color'] ?? null,
            'storage_gb' => $data['storage_gb'] ?? null,
            'year_released' => $data['year_released'] ?? null,
            'condition' => $data['condition'],
            'original_price' => $data['original_price'] ?? $listing->original_price ?? $data['price'],
            'price' => $data['price'],
            'weight' => $data['weight'] ?? 400,
            'city' => $city,
            'areas' => $areas,
            'lat' => $data['lat'] ?? $listing->lat ?? $geo['lat'],
            'lng' => $data['lng'] ?? $listing->lng ?? $geo['lng'],
            'extras' => $this->extrasFrom($data),
            'status' => $status === 'sold' ? 'sold' : $status,
            'published_at' => $status === 'active'
                ? ($listing->published_at ?? now())
                : $listing->published_at,
        ]);
        $fresh = $listing->fresh('brand');
        $fresh->original_price = $data['original_price'] ?? $fresh->original_price;
        $listing->estimated_price = ! empty($data['original_price'])
            ? $pricing->forListing($fresh)
            : (int) $data['price'];
        $listing->save();

        $this->storeImages($request, $listing, $media);
        if ($listing->images()->count() < 1) {
            return back()->withInput()->withErrors(['images' => 'Tin phải có ít nhất 1 ảnh sản phẩm.']);
        }
        $origin->upsert($listing, $data, $this->optionalFile($request, 'invoice', $media, 'origins'), $this->optionalFile($request, 'box_photo', $media, 'origins'));

        return redirect()->route('seller.listings.index')->with('success', 'Đã cập nhật tin trên chợ.');
    }

    public function estimate(Request $request, PricingEngine $pricing)
    {
        $data = $request->validate([
            'original_price' => 'required|integer|min:10000',
            'condition' => ['required', Rule::in(array_keys(Listing::CONDITIONS))],
            'year_released' => 'nullable|integer|min:2008|max:' . now()->year,
            'brand_id' => 'nullable|exists:brands,id',
        ]);

        $brand = ! empty($data['brand_id']) ? Brand::find($data['brand_id']) : null;

        return response()->json([
            'estimated' => $pricing->estimate($data, $brand),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'title' => 'required|string|max:140',
            'description' => 'required|string|min:10|max:5000',
            'model' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:40',
            'storage_gb' => 'nullable|integer|min:8|max:4096',
            'year_released' => 'nullable|integer|min:2008|max:' . now()->year,
            'condition' => ['required', Rule::in(array_keys(Listing::CONDITIONS))],
            'original_price' => 'nullable|integer|min:10000|max:200000000',
            'price' => 'required|integer|min:10000|max:200000000',
            'weight' => 'required|integer|min:50|max:30000',
            'areas' => 'required|array|min:1|max:1',
            'areas.*' => ['required', 'string', Rule::in(AreaService::names())],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'serial' => 'nullable|string|max:64',
            'imei' => 'nullable|string|max:32',
            'purchase_channel' => 'nullable|string|max:80',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'images' => ($request->routeIs('seller.listings.store') ? 'required' : 'nullable') . '|array|max:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
            'invoice' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'box_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'has_box' => 'nullable|boolean',
            'has_warranty' => 'nullable|boolean',
            'warranty_months' => 'nullable|integer|min:0|max:36',
            'scratch_note' => 'nullable|string|max:240',
        ]);
    }

    public function hide(Listing $listing)
    {
        $this->authorizeSeller($listing);
        abort_if($listing->status === 'sold', 403);
        $listing->update(['status' => 'hidden']);

        return back()->with('success', 'Đã ẩn tin khỏi chợ.');
    }

    public function publish(Listing $listing)
    {
        $this->authorizeSeller($listing);
        abort_unless(in_array($listing->status, ['hidden', 'rejected'], true), 403);

        $listing->update([
            'status' => 'active',
            'published_at' => $listing->published_at ?? now(),
        ]);

        return back()->with('success', 'Tin đã hiện lại trên chợ.');
    }

    public function markSold(Listing $listing)
    {
        $this->authorizeSeller($listing);
        abort_unless($listing->isActive() || $listing->status === 'hidden', 403);
        $listing->update(['status' => 'sold', 'sold_at' => now()]);

        return back()->with('success', 'Đã đánh dấu đã bán.');
    }

    public function boost(Listing $listing, WalletService $wallet)
    {
        $this->authorizeSeller($listing);
        abort_unless($listing->isActive(), 403);

        try {
            $wallet->debit(
                Auth::user(),
                WalletService::BOOST_FEE,
                'boost',
                'Đẩy tin #' . $listing->id . ' 7 ngày'
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $listing->update([
            'is_featured' => true,
            'featured_until' => now()->addDays(7),
        ]);

        return back()->with('success', 'Tin đã được đẩy lên mục nổi bật trong 7 ngày.');
    }

    private function storeImages(Request $request, Listing $listing, MediaService $media): void
    {
        $files = $request->file('images');
        $file = is_array($files) ? ($files[0] ?? null) : $files;
        if (! $file) {
            return;
        }

        $listing->images()->delete();
        $listing->images()->create([
            'path' => $media->storePublic($file, 'listings'),
            'sort_order' => 1,
        ]);
    }

    private function optionalFile(Request $request, string $key, MediaService $media, string $folder): ?string
    {
        return $request->hasFile($key) ? $media->storePublic($request->file($key), $folder) : null;
    }

    private function extrasFrom(array $data): array
    {
        return [
            'has_box' => ! empty($data['has_box']),
            'has_warranty' => ! empty($data['has_warranty']),
            'warranty_months' => $data['warranty_months'] ?? null,
            'scratch_note' => $data['scratch_note'] ?? null,
        ];
    }

    private function pingAlerts(Listing $listing): void
    {
        SearchAlert::query()->chunkById(100, function ($alerts) use ($listing) {
            foreach ($alerts as $alert) {
                if ($alert->matchesListing($listing)) {
                    $alert->increment('hits');
                    $alert->update(['last_hit_at' => now()]);
                }
            }
        });
    }

    private function authorizeSeller(Listing $listing): void
    {
        abort_unless($listing->seller_id === Auth::id(), 403);
    }
}
