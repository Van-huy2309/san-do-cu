@extends('layouts.seller')
@section('title', 'Tài khoản ngân hàng')
@section('content')
<h1>Đăng ký tài khoản nhận tiền</h1>
<p class="muted">Khi khách mua, tiền vào tài khoản chung của admin. Sau khi nhận hàng, hệ thống trừ 5% phí sàn và ghi nhận phần còn lại chuyển về tài khoản này. Số tài khoản được mã hóa, không lưu trong database chợ.</p>
<form method="post" action="{{ route('seller.bank.update') }}" class="panel" style="max-width:520px">
    @csrf
    <label>Ngân hàng</label>
    <select class="field" name="bank_name" required>
        @foreach ($banks as $bank)
            <option value="{{ $bank }}" @selected(old('bank_name', $account?->bank_name) === $bank)>{{ $bank }}</option>
        @endforeach
    </select>
    <label>Chủ tài khoản</label>
    <input class="field" name="account_holder" value="{{ old('account_holder') }}" required placeholder="NGUYEN VAN A">
    <label>Số tài khoản</label>
    <input class="field" name="account_number" value="{{ old('account_number') }}" required inputmode="numeric" placeholder="Chỉ nhập số">
    @if ($account)
        <p class="muted">Đang lưu: {{ $account->masked() }}. Nhập lại số đầy đủ nếu muốn đổi.</p>
    @endif
    <button class="btn btn-accent">Lưu tài khoản</button>
</form>
@endsection
