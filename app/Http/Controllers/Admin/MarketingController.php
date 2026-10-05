<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingEnrollment;
use App\Models\MarketingPackage;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function index()
    {
        $packages = MarketingPackage::query()
            ->withCount(['enrollments as taken_slots' => fn ($query) => $query->where('ends_at', '>', now())])
            ->latest()
            ->get();
        $enrollments = MarketingEnrollment::query()
            ->with(['package', 'listing', 'seller'])
            ->where('ends_at', '>', now())
            ->latest()
            ->limit(40)
            ->get();

        return view('admin.marketing.index', compact('packages', 'enrollments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'slot_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        MarketingPackage::create($data + ['is_active' => true]);

        return back()->with('success', 'Đã tạo gói marketing. Người bán chỉ đăng ký được trong số suất này.');
    }

    public function toggle(MarketingPackage $package)
    {
        $package->update(['is_active' => ! $package->is_active]);

        return back()->with('success', $package->is_active ? 'Đã mở gói.' : 'Đã đóng gói. Sản phẩm không còn hiện ở mục quảng cáo.');
    }
}
