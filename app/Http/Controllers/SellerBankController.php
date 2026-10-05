<?php

namespace App\Http\Controllers;

use App\Services\FinanceBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SellerBankController extends Controller
{
    public const BANKS = [
        'Vietcombank', 'VietinBank', 'BIDV', 'Agribank', 'Techcombank',
        'MB Bank', 'ACB', 'VPBank', 'TPBank', 'Sacombank',
    ];

    public function edit(FinanceBook $finance)
    {
        abort_if(Auth::user()->isAdmin(), 403);

        return view('seller.bank', [
            'account' => $finance->bankFor((int) Auth::id()),
            'banks' => self::BANKS,
        ]);
    }

    public function update(Request $request, FinanceBook $finance)
    {
        abort_if($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'bank_name' => ['required', Rule::in(self::BANKS)],
            'account_holder' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
        ]);

        $finance->saveBank(
            (int) $request->user()->id,
            $data['bank_name'],
            $data['account_holder'],
            $data['account_number']
        );

        return redirect()
            ->route('seller.listings.index')
            ->with('success', 'Đã lưu tài khoản nhận tiền. Số tài khoản được mã hóa trong cơ sở dữ liệu tài chính riêng.');
    }
}
