@php
    $sectionStats = $sectionStats ?? [];
@endphp
@if ($sectionStats !== [])
    <div class="stats section-stats">
        @foreach ($sectionStats as $label => $value)
            <div class="stat"><b>{!! $value !!}</b><span>{{ $label }}</span></div>
        @endforeach
    </div>
@endif
