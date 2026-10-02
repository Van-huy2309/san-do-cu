@extends('layouts.store')
@section('title', 'Xác thực nguồn gốc')
@section('content')
<div class="panel">
    <h1>Relic Seal</h1>
    <p>Serial và IMEI không lưu plaintext. Relic băm SHA-256, giữ 4 số cuối để đối soát khi giao. Hóa đơn và ảnh hộp được lưu riêng. Admin xác nhận thì tin nhận mã RLC-xxxxxxxxxxxx.</p>
    <p>Lớp AI nhận diện ảnh/hóa đơn sẽ gắn vào sau khi sản phẩm chợ đã chạy ổn.</p>
</div>
@endsection
