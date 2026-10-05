<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SellerVoucherController extends Controller
{
    public function index()
    {
        $this->guardSeller();
        $vouchers = Voucher::query()->where('seller_id', Auth::id())->latest()->get();

        return view('seller.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request)
    {
        $this->guardSeller();
        $data = $request->validate([
            'code' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('vouchers', 'code')],
            'name' => ['required', 'string', 'max:80'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'min_order' => ['nullable', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);
        if ($data['discount_type'] === 'percent' && (int) $data['discount_value'] > 100) {
            return back()->withErrors(['discount_value' => 'Phần trăm giảm không vượt quá 100.'])->withInput();
        }

        Voucher::create([
            'seller_id' => Auth::id(),
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_order' => $data['min_order'] ?? 0,
            'quantity' => $data['quantity'],
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã tạo phiếu của shop. Hết số lượt thì khách không dùng được nữa.');
    }

    public function toggle(Voucher $voucher)
    {
        $this->guardSeller();
        abort_unless((int) $voucher->seller_id === (int) Auth::id(), 403);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', $voucher->is_active ? 'Đã bật phiếu.' : 'Đã tắt phiếu.');
    }

    private function guardSeller(): void
    {
        abort_if(Auth::user()->isAdmin(), 403);
        abort_unless(Auth::user()->kycVerified(), 403, 'Cần KYC để tạo phiếu giảm giá.');
    }
}
