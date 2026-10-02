<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangeCodeMail;
use App\Models\Listing;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountController extends Controller
{
    private const PENDING_KEY = 'relic.pending_change';

    public const FIELDS = [
        'password' => 'Mật khẩu',
        'name' => 'Tên hiển thị',
        'phone' => 'Số điện thoại',
    ];

    public function profile(Request $request)
    {
        $request->session()->forget(self::PENDING_KEY);

        return view('account.profile', ['user' => Auth::user()]);
    }

    public function changeIndex(Request $request)
    {
        $request->session()->forget(self::PENDING_KEY);

        return view('account.change.index');
    }

    public function changeForm(Request $request, string $field)
    {
        abort_unless(isset(self::FIELDS[$field]), 404);
        $request->session()->forget(self::PENDING_KEY);

        return view('account.change.form', ['field' => $field, 'label' => self::FIELDS[$field]]);
    }

    public function changeStart(Request $request, string $field)
    {
        abort_unless(isset(self::FIELDS[$field]), 404);
        $user = $request->user();

        $value = match ($field) {
            'name' => $request->validate(['name' => 'required|string|max:80'])['name'],
            'phone' => $request->validate(['phone' => ['required', 'regex:/^0\d{9}$/']])['phone'],
            'password' => Hash::make($request->validate(['password' => 'required|confirmed|min:8'])['password']),
        };

        if ($field === 'password') {
            $old = $request->validate(['current_password' => 'required|string'])['current_password'];
            if (! Hash::check($old, $user->password)) {
                return back()->with('error', 'Mật khẩu cũ không đúng.')->withInput();
            }
        }

        $code = (string) random_int(100000, 999999);
        $request->session()->put(self::PENDING_KEY, [
            'field' => $field,
            'value' => $value,
            'code' => $code,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        try {
            Mail::to($user->email)->send(new PasswordChangeCodeMail($user->name, $code, self::FIELDS[$field]));
        } catch (\Exception $e) {
            Log::error('Change code mail failed: ' . $e->getMessage());
            $request->session()->forget(self::PENDING_KEY);

            return back()->with('error', 'Không gửi được mã xác thực. Kiểm tra cấu hình MAIL trong .env.')->withInput();
        }

        return redirect()->route('account.change.confirm')
            ->with('success', 'Đã gửi mã xác thực về ' . $user->email . '. Mã hết hạn sau 10 phút.');
    }

    public function changeConfirm(Request $request)
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('account.change.index')->with('warning', 'Yêu cầu đã hết hạn hoặc bị hủy. Hãy bắt đầu lại.');
        }

        return view('account.change.confirm', [
            'field' => $pending['field'],
            'label' => self::FIELDS[$pending['field']],
            'preview' => $pending['field'] === 'password' ? '••••••••' : $pending['value'],
        ]);
    }

    public function changeApply(Request $request)
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('account.change.index')->with('warning', 'Yêu cầu đã hết hạn hoặc bị hủy. Hãy bắt đầu lại.');
        }

        $data = $request->validate([
            'code' => 'required|digits:6',
            'current_password' => 'required|string',
        ]);

        if (! hash_equals($pending['code'], $data['code'])) {
            return back()->with('error', 'Mã xác thực không đúng.');
        }

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->with('error', 'Mật khẩu xác thực không đúng.');
        }

        $request->user()->forceFill([$pending['field'] => $pending['value']])->save();
        $request->session()->forget(self::PENDING_KEY);

        return redirect()->route('account.profile')
            ->with('success', 'Đã đổi ' . mb_strtolower(self::FIELDS[$pending['field']]) . '.');
    }

    public function changeResend(Request $request)
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('account.change.index')->with('warning', 'Yêu cầu đã hết hạn hoặc bị hủy. Hãy bắt đầu lại.');
        }

        $code = (string) random_int(100000, 999999);
        $pending['code'] = $code;
        $pending['expires_at'] = now()->addMinutes(10)->timestamp;
        $request->session()->put(self::PENDING_KEY, $pending);

        try {
            Mail::to($request->user()->email)->send(
                new PasswordChangeCodeMail($request->user()->name, $code, self::FIELDS[$pending['field']])
            );
        } catch (\Exception $e) {
            Log::error('Resend change code failed: ' . $e->getMessage());

            return back()->with('error', 'Không gửi được mã xác thực.');
        }

        return back()->with('success', 'Đã gửi lại mã xác thực.');
    }

    public function changeCancel(Request $request)
    {
        $request->session()->forget(self::PENDING_KEY);

        return redirect()->route('account.profile')->with('warning', 'Đã hủy thay đổi. Không có thông tin nào được lưu.');
    }

    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! is_array($pending) || ! isset(self::FIELDS[$pending['field'] ?? ''])) {
            return null;
        }

        if (($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_KEY);

            return null;
        }

        return $pending;
    }

    public function storeReview(Request $request, Listing $listing)
    {
        abort_unless(in_array($listing->status, ['sold', 'reserved', 'active'], true), 403);
        abort_if($listing->seller_id === Auth::id(), 403);

        $bought = $request->user()->orders()
            ->whereIn('status', ['paid', 'cod_ordered', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('listing_id', $listing->id))
            ->exists();
        abort_unless($bought, 403, 'Chỉ người đã mua mới đánh giá.');

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        Review::updateOrCreate(
            ['reviewer_id' => Auth::id(), 'listing_id' => $listing->id],
            [
                'seller_id' => $listing->seller_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]
        );

        $seller = $listing->seller;
        $avg = round((float) ($seller->receivedReviews()->avg('rating') ?? 0), 1);
        $seller->update([
            'rating_avg' => max(0, min(5, (int) round($avg))),
            'rating_count' => $seller->receivedReviews()->count(),
        ]);

        return back()->with('success', 'Cảm ơn bạn đã đánh giá.');
    }
}
