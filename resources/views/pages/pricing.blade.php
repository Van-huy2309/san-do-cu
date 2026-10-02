@extends('layouts.store')
@section('title', 'Định giá')
@section('content')
<div class="panel">
    <h1>Định giá Relic</h1>
    <p>Hiện tại dùng engine rule-based: giá hãng × hệ số tình trạng × khấu hao năm × hệ số thương hiệu. Kết quả làm tròn 10.000₫, chỉ là khung tham chiếu — người bán tự chốt giá. Module AI sẽ thay thế bước này sau.</p>
</div>
@endsection
