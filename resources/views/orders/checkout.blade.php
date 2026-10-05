@extends('layouts.store')
@section('title', 'Thanh toán')
@section('content')
<h1>Thanh toán</h1>
<div class="detail">
    <form action="{{ route('user.payment.process') }}" method="POST" class="panel">
        @csrf
        <h3>Giao hàng GHN</h3>
        <p class="muted">Phí ship hiện ngay khi chọn phường/xã (API GHN sandbox). Quản lý vận đơn: <a href="https://5sao.ghn.dev" target="_blank" rel="noopener">5sao.ghn.dev</a> · tính cước GHN: <a href="https://khachhang.ghn.vn" target="_blank" rel="noopener">khachhang.ghn.vn</a></p>
        <label>Họ tên</label>
        <input class="field" name="name" value="{{ old('name', auth()->user()->name) }}" required>
        <label>Số điện thoại</label>
        <input class="field" name="phone" value="{{ old('phone', auth()->user()->phone) }}" required>
        @error('phone')<div class="flash flash-err">{{ $message }}</div>@enderror
        <label>Địa chỉ chi tiết</label>
        <input class="field" name="address" value="{{ old('address') }}" required>
        <label>Tỉnh / Thành</label>
        <select id="province_select" class="field"><option>-- Đang tải --</option></select>
        <label>Quận / Huyện</label>
        <select id="district_select" name="to_district_id" class="field" disabled required><option value="">-- Chọn --</option></select>
        <label>Phường / Xã</label>
        <select id="ward_select" name="to_ward_code" class="field" disabled required><option value="">-- Chọn --</option></select>
        <h3>Phương thức thanh toán</h3>
        <div class="pay-grid">
            <label class="pay-card">
                <input type="radio" name="payment_method" value="momo" checked>
                <strong>Thanh toán ngay</strong>
                <span class="muted">MoMo sandbox payWithATM. Relic chốt đơn khi MoMo redirect về — IPN localhost không bắt buộc. Không dùng Visa 4111 cho đơn lớn (dễ lỗi 1002).</span>
            </label>
            <label class="pay-card">
                <input type="radio" name="payment_method" value="cod">
                <strong>Thanh toán khi nhận hàng</strong>
                <span class="muted">Trả tiền mặt cho shipper GHN khi nhận máy. Nếu GHN lỗi, đơn vẫn được ghi nhận (lab 06).</span>
            </label>
        </div>
        <p class="muted" style="margin-top:12px">Thẻ ATM test MoMo (lab 06), hạn 12/30, OTP bất kỳ:</p>
        <ul class="muted" style="margin:0 0 12px 18px">
            <li>9704 0000 0000 0018 — NGUYEN VAN A — thành công</li>
            <li>9704 0000 0000 0026 — thẻ khóa</li>
            <li>9704 0000 0000 0034 — không đủ tiền</li>
            <li>9704 0000 0000 0042 — vượt hạn mức</li>
        </ul>
        <label>Mã giảm giá</label>
        <div class="meta" style="gap:8px;align-items:center">
            <input class="field" id="voucher_code" name="voucher_code" value="{{ old('voucher_code') }}" placeholder="Nhập mã của sàn hoặc shop" style="flex:1">
            <button class="btn btn-ghost" type="button" id="apply_voucher">Xem mã</button>
        </div>
        <p class="muted" id="voucher_message"></p>
        <input type="hidden" id="total_price_input" value="{{ (int) $totalPrice }}">
        <button class="btn btn-accent" style="margin-top:16px;width:100%">Đặt hàng</button>
    </form>
    <div class="panel">
        <h3>Đơn Relic</h3>
        @foreach ($cart as $item)
            <div class="meta" style="justify-content:space-between"><span>{{ $item['name'] }}</span><strong>{{ number_format($item['price'], 0, ',', '.') }} ₫</strong></div>
        @endforeach
        <p>Tiền hàng: <strong>{{ number_format($totalPrice, 0, ',', '.') }} ₫</strong></p>
        <p>Giảm giá: <strong id="discount_text">0 ₫</strong></p>
        <p>Phí GHN: <strong id="shipping_fee_text">0 ₫</strong></p>
        <p>Tổng: <strong id="final_total_text">{{ number_format($totalPrice, 0, ',', '.') }} ₫</strong></p>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    const provinceSelect = document.getElementById('province_select');
    const districtSelect = document.getElementById('district_select');
    const wardSelect = document.getElementById('ward_select');
    const shippingFeeText = document.getElementById('shipping_fee_text');
    const discountText = document.getElementById('discount_text');
    const finalTotalText = document.getElementById('final_total_text');
    const districtsUrl = "{{ route('locations.districts', ['provinceId' => '__PROVINCE__']) }}";
    const wardsUrl = "{{ route('locations.wards', ['districtId' => '__DISTRICT__']) }}";
    const subtotal = parseInt(document.getElementById('total_price_input').value) || 0;
    let shippingFee = 0;
    let discount = 0;
    function money(value) {
        return new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
    }
    function updateTotals() {
        shippingFeeText.innerText = money(shippingFee);
        discountText.innerText = money(discount);
        finalTotalText.innerText = money(Math.max(0, subtotal - discount) + shippingFee);
    }
    fetch("{{ route('locations.provinces') }}", { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(res => {
            const list = Array.isArray(res.data) ? res.data : [];
            provinceSelect.innerHTML = '<option value="">-- Chọn Tỉnh/Thành --</option>' + list.map(p => `<option value="${p.ProvinceID ?? p.province_id}">${p.ProvinceName ?? p.province_name}</option>`).join('');
        }).catch(() => { provinceSelect.innerHTML = '<option>Không tải được GHN</option>'; });
    provinceSelect.addEventListener('change', function () {
        districtSelect.disabled = true; wardSelect.disabled = true; shippingFee = 0; updateTotals();
        if (!this.value) return;
        fetch(districtsUrl.replace('__PROVINCE__', this.value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(res => {
                const list = Array.isArray(res.data) ? res.data : [];
                districtSelect.innerHTML = '<option value="">-- Chọn --</option>' + list.map(d => `<option value="${d.DistrictID ?? d.district_id}">${d.DistrictName ?? d.district_name}</option>`).join('');
                districtSelect.disabled = false;
            });
    });
    districtSelect.addEventListener('change', function () {
        wardSelect.disabled = true; shippingFee = 0; updateTotals();
        if (!this.value) return;
        fetch(wardsUrl.replace('__DISTRICT__', this.value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(res => {
                const list = Array.isArray(res.data) ? res.data : [];
                wardSelect.innerHTML = '<option value="">-- Chọn --</option>' + list.map(w => `<option value="${w.WardCode ?? w.ward_code}">${w.WardName ?? w.ward_name}</option>`).join('');
                wardSelect.disabled = false;
            });
    });
    wardSelect.addEventListener('change', function () {
        if (!this.value) return;
        fetch("{{ route('locations.fee') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ to_district_id: districtSelect.value, to_ward_code: this.value })
        }).then(r => r.json()).then(res => {
            shippingFee = res.code === 200 && res.data ? parseInt(res.data.total) || 0 : 0;
            updateTotals();
        });
    });
    document.getElementById('apply_voucher').addEventListener('click', function () {
        const message = document.getElementById('voucher_message');
        fetch("{{ route('user.payment.voucher') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ voucher_code: document.getElementById('voucher_code').value })
        }).then(r => r.json()).then(res => {
            discount = res.ok ? parseInt(res.discount) || 0 : 0;
            message.textContent = res.message || '';
            updateTotals();
        }).catch(() => { message.textContent = 'Không kiểm tra được mã.'; });
    });
});
</script>
@endpush
