<p>Xin chào {{ $userName }},</p>
@if ($locked)
    <p>Tài khoản Relic của bạn đã bị khóa.</p>
    <p><strong>Lý do:</strong> {{ $reason }}</p>
@else
    <p>Tài khoản Relic của bạn đã được mở khóa. Bạn có thể đăng nhập lại.</p>
@endif
