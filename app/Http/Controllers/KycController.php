<?php

namespace App\Http\Controllers;

use App\Services\MediaService;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function show(Request $request)
    {
        return view('account.kyc', ['user' => $request->user()]);
    }

    public function store(Request $request, MediaService $media)
    {
        $user = $request->user();
        if ($user->kyc_status === 'verified') {
            return back()->with('success', 'Tài khoản đã định danh.');
        }
        if ($user->kyc_status === 'pending') {
            return back()->with('warning', 'Hồ sơ KYC đang chờ duyệt.');
        }

        $data = $request->validate([
            'kyc_full_name' => 'required|string|max:80',
            'id_number' => ['required', 'regex:/^\d{9,12}$/'],
            'kyc_front' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'kyc_back' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $user->update([
            'kyc_full_name' => $data['kyc_full_name'],
            'kyc_id_last4' => substr($data['id_number'], -4),
            'kyc_front_path' => $media->storePublic($request->file('kyc_front'), 'kyc'),
            'kyc_back_path' => $media->storePublic($request->file('kyc_back'), 'kyc'),
            'kyc_status' => 'pending',
            'kyc_note' => null,
        ]);

        return back()->with('success', 'Đã gửi CCCD. Relic sẽ duyệt trước khi bạn bán hàng.');
    }
}
