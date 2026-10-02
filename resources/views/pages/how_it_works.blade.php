@extends('layouts.store')
@section('title', 'Cách mua bán')
@section('content')
<div class="panel">
    <h1>Luồng Relic</h1>
    <h3>Người bán</h3>
    <ol>
        <li>Xác minh CCCD (KYC) → admin duyệt.</li>
        <li>Đăng tin: ảnh, tình trạng, hộp/BH, hồ sơ nguồn gốc (serial).</li>
        <li>Ẩn / hiện / đánh dấu đã bán / đẩy tin bằng ví.</li>
        <li>Nhận tiền vào ví sau escrow (trừ 5%).</li>
    </ol>
    <h3>Người mua</h3>
    <ol>
        <li>Lọc theo hãng, tình trạng, giá, Relic Seal, gần tỉnh hồ sơ.</li>
        <li>Lưu yêu thích để xem lại tin.</li>
        <li>Chat và trả giá; shop đồng ý thì mua theo giá chốt.</li>
        <li>MoMo: sàn giữ tiền. Khi nhận đúng mô tả → giải ngân. Sai → khiếu nại kèm video.</li>
    </ol>
    <h3>Admin</h3>
    <ol>
        <li>Duyệt KYC, Relic Seal, tin rác.</li>
        <li>Xử lý khiếu nại: hoàn ví người mua hoặc giải ngân người bán.</li>
        <li>Đối soát nạp/rút ví, theo dõi escrow đang giữ.</li>
    </ol>
    <h3>Tìm kiếm &amp; chat</h3>
    <ol>
        <li>Full-text Elasticsearch (nếu <code>ELASTICSEARCH_HOST</code>), không có thì SQL.</li>
        <li>Lọc GPS bán kính (trình duyệt / app Flutter).</li>
        <li>Chat Relic Reverb realtime; nếu WS tắt thì poll 3 giây.</li>
        <li>Ảnh lên S3 khi điền AWS_BUCKET; không thì ổ <code>public</code>.</li>
        <li>App Flutter trong thư mục <code>mobile/</code>.</li>
    </ol>
</div>
@endsection
