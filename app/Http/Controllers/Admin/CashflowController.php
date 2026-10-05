<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FinanceBook;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowController extends Controller
{
    public function index(FinanceBook $finance)
    {
        $report = $finance->adminReport(14, 40);
        $entries = $report['entries'];
        $accounts = $report['accounts'];
        $names = User::query()->whereIn('id', collect($entries)->pluck('seller_id')->filter()->unique())->pluck('name', 'id');
        $shopNames = User::query()->whereIn('id', collect($accounts)->pluck('user_id'))->pluck('name', 'id');

        return view('admin.cashflow.index', [
            'totals' => $report['totals'],
            'series' => $report['series'],
            'entries' => $entries,
            'names' => $names,
            'accounts' => $accounts,
            'shopNames' => $shopNames,
        ]);
    }

    public function export(FinanceBook $finance): StreamedResponse
    {
        $entries = $finance->adminReport(14, 0)['entries'];
        $names = User::query()->whereIn('id', collect($entries)->pluck('seller_id')->filter()->unique())->pluck('name', 'id');

        return response()->streamDownload(function () use ($entries, $names) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Thời điểm', 'Đơn', 'Shop', 'Loại', 'Số tiền']);
            foreach ($entries as $entry) {
                fputcsv($out, [
                    $entry->occurred_at?->format('Y-m-d H:i'),
                    $entry->order_id,
                    $names[$entry->seller_id] ?? ($entry->seller_id ? '#'.$entry->seller_id : 'Tài khoản admin'),
                    $entry->typeLabel(),
                    $entry->amount,
                ]);
            }
            fclose($out);
        }, 'dong-tien-relic.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
