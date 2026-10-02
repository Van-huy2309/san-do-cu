@extends('layouts.admin')
@section('title', 'Báo cáo')
@section('content')
<h1>Báo cáo tin</h1>
@include('admin.partials.section-insights')
<table class="table">
    <tr><th>Tin</th><th>Người báo</th><th>Lý do</th><th>TT</th><th></th></tr>
    @foreach ($reports as $report)
        <tr>
            <td>{{ $report->listing->title ?? '—' }}</td>
            <td>{{ $report->user->name }}</td>
            <td>{{ $report->reason }}</td>
            <td>{{ $report->status }}</td>
            <td>
                @if ($report->status === 'open')
                <form method="post" action="{{ route('admin.reports.close', $report) }}">@csrf<button class="btn btn-sm">Đóng</button></form>
                @endif
            </td>
        </tr>
    @endforeach
</table>
{{ $reports->links() }}
@endsection
