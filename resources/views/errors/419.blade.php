@extends('layouts.store')
@section('title', 'Phiên hết hạn')
@section('content')
<div class="panel" style="max-width:560px;margin:40px auto;text-align:center">
    <h1>Phiên làm việc hết hạn</h1>
    <p class="muted">Form đã cũ hoặc cookie phiên không còn hợp lệ.</p>
    <a class="btn btn-accent" href="{{ route('home') }}">Về Relic</a>
</div>
@endsection
