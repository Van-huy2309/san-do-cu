<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::query()->whereNull('seller_id')->latest()->get();

        return view('admin.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Voucher::create([
            'seller_id' => null,
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_order' => $data['min_order'] ?? 0,
            'quantity' => $data['quantity'],
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã tạo phiếu giảm giá của sàn.');
    }

    public function toggle(Voucher $voucher)
    {
        abort_unless($voucher->seller_id === null, 404);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        return back()->with('success', $voucher->is_active ? 'Đã bật phiếu.' : 'Đã tắt phiếu.');
    }

    private function validated(Request $request): array
    {
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_value' => 'Phần trăm giảm không vượt quá 100.',
            ]);
        }

        return $data;
    }
}
