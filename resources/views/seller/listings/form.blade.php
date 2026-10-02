@extends('layouts.account')
@section('title', $listing->exists ? 'Sửa tin' : 'Đăng bán')
@section('content')
<h1>{{ $listing->exists ? 'Sửa tin' : 'Đăng bán máy cũ' }}</h1>
<p class="muted">Điền giá bạn muốn bán, một ảnh thật và khu vực. Gửi xong tin lên chợ ngay.</p>
<form class="panel" method="post" enctype="multipart/form-data" action="{{ $listing->exists ? route('seller.listings.update', $listing) : route('seller.listings.store') }}" data-listing-form>
    @csrf
    @if ($listing->exists) @method('put') @endif
    <div class="form-grid">
        <div class="span-2">
            <label>Tiêu đề</label>
            <input class="field" name="title" value="{{ old('title', $listing->title) }}" required maxlength="140" placeholder="Ví dụ: iPhone 12 64GB đen, pin 87%">
        </div>
        <div>
            <label>Danh mục</label>
            <select class="field" name="category_id" required>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $listing->category_id)==$c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Thương hiệu</label>
            <select class="field" name="brand_id" required>
                <option value="">Chọn hãng</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->id }}" @selected(old('brand_id', $listing->brand_id)==$b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Model</label>
            <input class="field" name="model" value="{{ old('model', $listing->model) }}" required maxlength="80" placeholder="iPhone 12, Galaxy S22…">
        </div>
        <div>
            <label>Tình trạng</label>
            <select class="field" name="condition">
                @foreach (\App\Models\Listing::CONDITIONS as $k=>$label)
                    <option value="{{ $k }}" @selected(old('condition', $listing->condition ?: 'good')===$k)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Giá bán (₫)</label>
            <input class="field" type="number" name="price" min="10000" max="200000000" value="{{ old('price', $listing->exists ? $listing->price : '') }}" required placeholder="Bạn tự nhập giá muốn bán">
        </div>
        <div>
            <label>Khối lượng (gram)</label>
            <input class="field" type="number" name="weight" min="50" max="30000" value="{{ old('weight', $listing->weight ?: 400) }}" required>
        </div>
        <div>
            <label>Tỉnh / thành bán</label>
            @php $picked = old('areas.0', ($listing->areaList()[0] ?? null) ?: auth()->user()->city); @endphp
            <select class="field" name="areas[]" required>
                <option value="">Chọn khu vực</option>
                @foreach ($areas as $name)
                    <option value="{{ $name }}" @selected($picked === $name)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="span-2">
            <label>Vị trí máy</label>
            <input type="hidden" name="lat" data-geo-lat value="{{ old('lat', $listing->lat) }}">
            <input type="hidden" name="lng" data-geo-lng value="{{ old('lng', $listing->lng) }}">
            <button class="btn btn-ghost btn-sm" type="button" data-geo>Lấy vị trí máy</button>
            <p class="muted" data-geo-status>@if(old('lat', $listing->lat)) Đã có vị trí đã lưu. @else Bấm nút để gắn vị trí, hoặc bỏ qua và chỉ dùng tỉnh/thành. @endif</p>
        </div>
        <div class="span-2">
            <label>Mô tả</label>
            <textarea class="field" name="description" rows="4" required minlength="10" maxlength="5000" placeholder="Pin, trầy xước, phụ kiện còn thiếu">{{ old('description', $listing->description) }}</textarea>
        </div>
        <div>
            <label><input type="hidden" name="has_box" value="0"><input type="checkbox" name="has_box" value="1" @checked(old('has_box', $listing->extra('has_box')))> Còn hộp</label>
        </div>
        <div>
            <label><input type="hidden" name="has_warranty" value="0"><input type="checkbox" name="has_warranty" value="1" @checked(old('has_warranty', $listing->extra('has_warranty')))> Còn bảo hành</label>
        </div>
        <div class="span-2">
            <label>Serial / IMEI <span class="muted">(không bắt buộc)</span></label>
            <input class="field" name="serial" value="{{ old('serial') }}" maxlength="64" placeholder="Để admin đối soát nguồn gốc nếu bạn có">
        </div>
        <div class="span-2">
            <label>Ảnh sản phẩm @if(!$listing->exists)<span class="muted">(1 ảnh, tối đa 2MB)</span>@else<span class="muted">(để trống nếu giữ ảnh hiện tại)</span>@endif</label>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" @required(!$listing->exists)>
            @if ($listing->exists && $listing->images->isNotEmpty())
                <p class="muted">Ảnh hiện tại: {{ $listing->images->count() }} file. Chọn ảnh mới sẽ thay ảnh cũ.</p>
            @endif
        </div>
    </div>
    <button class="btn btn-accent" type="submit" data-submit>{{ $listing->exists ? 'Lưu tin' : 'Đăng bán' }}</button>
</form>
@endsection
@push('scripts')
<script>
document.querySelector('[data-listing-form]')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    if (!form.checkValidity()) return;
    const btn = form.querySelector('[data-submit]');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.textContent = 'Đang gửi…';
});
</script>
@endpush
