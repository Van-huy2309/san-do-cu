@extends('layouts.auth')
@section('title', 'Đăng nhập')
@section('content')
<h2>Đăng nhập</h2>
<p class="sub">Chào mừng trở lại Relic. Dùng email và mật khẩu để vào tài khoản.</p>
<form action="{{ route('login') }}" method="POST">
    @csrf
    <div class="field-group">
        <label class="field-label" for="email">Email</label>
        <input type="email" name="email" id="email" class="field field-input" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field-group">
        <label class="field-label" for="password">Mật khẩu</label>
        <div class="pw-wrap">
            <input type="password" name="password" id="password" class="field field-input" required>
            <button type="button" class="pw-toggle" data-pw-toggle aria-label="Hiện mật khẩu">Hiện</button>
        </div>
        @error('password')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <label class="remember-row">
        <input type="checkbox" name="remember" value="1"> Ghi nhớ đăng nhập
    </label>
    <div class="field-group" style="position:absolute;left:-9999px" aria-hidden="true">
        <input type="text" name="company" tabindex="-1" autocomplete="off">
    </div>
    <label class="human-row" for="human-check">
        <input type="checkbox" id="human-check">
        <span>Tôi là người</span>
    </label>
    <button type="submit" class="btn btn-accent btn-auth" id="login-submit" disabled>Đăng nhập</button>
</form>
<script>
(() => {
    const box = document.getElementById('human-check');
    const submit = document.getElementById('login-submit');
    const form = box.closest('form');
    const prefix = @json($humanPrefix);
    const url = @json(route('login.human'));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    form.addEventListener('submit', (event) => {
        if (submit.disabled) event.preventDefault();
    });
    box.addEventListener('click', async (event) => {
        if (!event.isTrusted) {
            event.preventDefault();
            box.checked = false;
            submit.disabled = true;
            return;
        }
        if (!box.checked) {
            submit.disabled = true;
            return;
        }
        box.disabled = true;
        let solution = null;
        for (let n = 0; n < 8000 && solution === null; n++) {
            const data = new TextEncoder().encode(prefix + '|' + n);
            const buf = await crypto.subtle.digest('SHA-256', data);
            const hex = [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
            if (hex.startsWith('00')) solution = String(n);
            if (n % 30 === 0) await new Promise((resolve) => setTimeout(resolve, 0));
        }
        if (solution === null) {
            box.disabled = false;
            box.checked = false;
            return;
        }
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ solution })
        });
        if (!response.ok) {
            box.disabled = false;
            box.checked = false;
            return;
        }
        submit.disabled = false;
    });
})();
</script>
<p class="auth-link">Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký</a></p>
<p class="auth-link"><a href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
@endsection
