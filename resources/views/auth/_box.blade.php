@extends('layouts.store')
@section('title', $title ?? 'Tài khoản')
@section('content')
<div class="panel" style="max-width:440px;margin:40px auto">
    <h1>{{ $heading }}</h1>
    @yield('form')
</div>
@endsection
