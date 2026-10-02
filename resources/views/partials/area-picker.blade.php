@php
    $pickerName = $pickerName ?? 'city';
    $pickerValue = $pickerValue ?? ($currentArea ?? '');
    $pickerSubmit = $pickerSubmit ?? false;
@endphp
<div class="area-picker {{ $pickerSubmit ? 'is-quick' : '' }}" data-area-picker>
    <button type="button" class="area-picker-btn" aria-expanded="false" aria-haspopup="listbox" aria-label="Chọn khu vực">
        <span aria-hidden="true">📍</span>
        <span data-area-label>{{ $pickerValue !== '' ? $pickerValue : 'Toàn quốc' }}</span>
    </button>
    <input type="hidden" name="{{ $pickerName }}" value="{{ $pickerValue }}" data-area-input>
    <div class="area-picker-menu" hidden>
        <input type="search" class="area-picker-find" placeholder="Tìm tỉnh, thành phố..." aria-label="Lọc khu vực">
        <div class="area-picker-list" role="listbox">
            <button type="button" class="area-picker-opt {{ $pickerValue === '' ? 'is-on' : '' }}" data-value="">Toàn quốc</button>
            @foreach ($areas ?? [] as $areaName)
                <button type="button" class="area-picker-opt {{ $pickerValue === $areaName ? 'is-on' : '' }}" data-value="{{ $areaName }}">{{ $areaName }}</button>
            @endforeach
        </div>
    </div>
</div>
