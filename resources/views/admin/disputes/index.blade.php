@extends('layouts.admin')
@section('title', 'Khiếu nại')
@section('content')
<h1>Trung tâm tranh chấp</h1>
@include('admin.partials.section-insights')
@foreach ($disputes as $d)
    <div class="panel" style="margin-bottom:12px">
        <strong>Đơn {{ $d->order->code }}</strong> · {{ $d->statusLabel() }} · {{ $d->reasonLabel() }}
        <p>{{ $d->detail }}</p>
        @if ($d->evidence_path)<p><a href="{{ url($d->evidence_path) }}" target="_blank">Bằng chứng</a></p>@endif
        @if ($d->status === 'open')
            <form method="post" action="{{ route('admin.disputes.resolve', $d) }}">
                @csrf
                <select class="field" name="resolution">
                    <option value="release">Giải ngân người bán</option>
                    <option value="refund">Hoàn tiền người mua (vào ví)</option>
                </select>
                <input class="field" name="admin_note" placeholder="Ghi chú xử lý">
                <button class="btn btn-accent">Chốt khiếu nại</button>
            </form>
        @else
            <p class="muted">{{ $d->resolution }} · {{ $d->admin_note }}</p>
        @endif
    </div>
@endforeach
{{ $disputes->links() }}
@endsection
