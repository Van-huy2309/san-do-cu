@extends('layouts.admin')
@section('title', 'Danh mục')
@section('content')
<h1>Danh mục</h1>
<form method="post" action="{{ route('admin.categories.store') }}" class="panel" style="max-width:520px">
    @csrf
    <label>Tên</label>
    <input class="field" name="name" placeholder="Ví dụ: Đồng hồ" value="{{ old('name') }}" required>
    <label>Icon</label>
    <input class="field" name="icon" placeholder="⌚" value="{{ old('icon') }}">
    <label>Màu accent</label>
    <input class="field" name="accent" placeholder="#7c5cfc" value="{{ old('accent') }}">
    <label>Mô tả</label>
    <input class="field" name="description" placeholder="Mô tả ngắn" value="{{ old('description') }}">
    <button class="btn btn-accent">Thêm danh mục</button>
</form>
<table class="table">
    <tr>
        <th>Danh mục</th>
        <th>Slug</th>
        <th>Tin</th>
        <th>Hiển thị</th>
        <th></th>
    </tr>
    @foreach ($categories as $c)
        <tr>
            <td colspan="5">
                <form method="post" action="{{ route('admin.categories.update', $c) }}" class="panel" style="margin:0">
                    @csrf
                    @method('put')
                    <div class="meta" style="gap:8px;flex-wrap:wrap;align-items:flex-end">
                        <label>Icon <input class="field" name="icon" value="{{ $c->icon }}" style="width:4rem"></label>
                        <label>Tên <input class="field" name="name" value="{{ $c->name }}" required></label>
                        <label>Màu <input class="field" name="accent" value="{{ $c->accent }}"></label>
                        <label>Mô tả <input class="field" name="description" value="{{ $c->description }}"></label>
                        <label><input type="checkbox" name="is_active" value="1" @checked($c->is_active)> Hiện trên sàn</label>
                        <span class="muted">{{ $c->slug }} · {{ $c->listings_count }} tin @unless($c->is_active) · đang ẩn @endunless</span>
                        <button class="btn btn-sm">Lưu</button>
                    </div>
                </form>
                <form method="post" action="{{ route('admin.categories.destroy', $c) }}" onsubmit="return confirm('Xóa danh mục {{ $c->name }}?')" style="margin-top:8px">
                    @csrf
                    @method('delete')
                    <button class="btn btn-sm btn-danger">Xóa</button>
                </form>
            </td>
        </tr>
    @endforeach
</table>
@endsection
