<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\LoginJail;
use Exception;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'email' => 'required|email:filter|max:120|unique:users,email',
            'password' => 'required|confirmed|min:8',
        ]);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'customer',
            ]);
        } catch (Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Đăng ký thất bại. Vui lòng thử lại.');
        }

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->flash('relic.ai.open', true);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Exception $e) {
            Log::error('Verification email failed: ' . $e->getMessage());

            return redirect()->route('verification.notice')
                ->with('warning', 'Tài khoản đã tạo nhưng gửi email xác thực thất bại. Bấm gửi lại trên trang này.');
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Đăng ký thành công. Nhập mã 6 số đã gửi vào email — không cần bấm link trên điện thoại.');
    }

    public function showLoginForm(Request $request)
    {
        $redirect = $request->query('redirect');
        if (is_string($redirect) && str_starts_with($redirect, $request->root())) {
            $request->session()->put('url.intended', $redirect);
        }

        $prefix = Str::random(16);
        $request->session()->put('human', [
            'prefix' => $prefix,
            'at' => now()->timestamp,
        ]);
        $request->session()->forget('human_ok');

        return view('auth.login', ['humanPrefix' => $prefix]);
    }

    public function confirmHuman(Request $request)
    {
        if ($request->filled('company')) {
            return response()->json(['ok' => false], 422);
        }

        $human = $request->session()->get('human');
        $solution = (string) $request->input('solution', '');
        if (! is_array($human) || $solution === '' || strlen($solution) > 8 || ! ctype_digit($solution)) {
            return response()->json(['ok' => false, 'message' => 'Hãy bấm lại nút tôi là người.'], 422);
        }

        $age = now()->timestamp - (int) ($human['at'] ?? 0);
        if ($age < 0 || $age > 180) {
            return response()->json(['ok' => false, 'message' => 'Hãy bấm lại nút tôi là người.'], 422);
        }

        $hash = hash('sha256', $human['prefix'].'|'.$solution);
        if (! str_starts_with($hash, '00')) {
            return response()->json(['ok' => false, 'message' => 'Chưa xác nhận được. Bấm lại nút.'], 422);
        }

        $request->session()->put('human_ok', now()->timestamp);
        $request->session()->forget('human');

        return response()->json(['ok' => true]);
    }

    public function login(Request $request, LoginJail $jail)
    {
        $okAt = (int) $request->session()->get('human_ok', 0);
        if ($okAt < 1 || (now()->timestamp - $okAt) > 120) {
            return back()->with('error', 'Hãy bấm «Tôi là người» trên trang này trước khi đăng nhập.');
        }
        $request->session()->forget('human_ok');

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $banned = $jail->hit($request->ip());

            return back()->with('error', $banned ? $jail->message($request->ip()) : 'Thử đăng nhập quá nhiều. Vui lòng đợi một phút.');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $jail->clear($request->ip());
            $request->session()->regenerate();

            if (Auth::user()->is_banned) {
                $reason = Auth::user()->ban_reason ?: 'Tài khoản đã bị khóa.';
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->with('error', 'Tài khoản bị khóa: ' . $reason);
            }

            if (Auth::user()->isAdmin()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            $request->session()->flash('relic.ai.open', true);

            return redirect()->intended(route('home'));
        }

        RateLimiter::hit($key, 60);
        $banned = $jail->hit($request->ip());

        return back()->with('error', $banned ? $jail->message($request->ip()) : 'Email hoặc mật khẩu không đúng.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Đã gửi link đặt lại mật khẩu vào email.')
            : back()->with('error', 'Không gửi được. Kiểm tra email hoặc cấu hình MAIL trong .env.');
    }

    public function showResetForm(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Đã đổi mật khẩu. Hãy đăng nhập.')
            : back()->with('error', 'Link hết hạn hoặc không hợp lệ.');
    }

    public function sendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Exception $e) {
            Log::error('Resend verification failed: ' . $e->getMessage());

            return back()->with('error', 'Không gửi được email xác thực. Thử lại hoặc kiểm tra hộp Spam.');
        }

        return back()->with('message', 'Đã gửi mã 6 số vào email. Mở hộp thư rồi nhập mã tại đây.');
    }

    public function confirmVerification(Request $request, EmailVerificationService $codes)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        try {
            $codes->confirm($request->user()->fresh(), $data['code']);
            $request->user()->refresh();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->intended(route('home'))
            ->with('success', 'Xác thực email thành công. Bạn có thể mua hàng và đăng bán.');
    }
}
