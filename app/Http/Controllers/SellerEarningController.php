<?php

namespace App\Http\Controllers;

use App\Models\Finance\LedgerEntry;
use App\Models\OrderItem;
use App\Services\FinanceBook;
use App\Services\WalletService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerEarningController extends Controller
{
    public function index(FinanceBook $finance)
    {
        abort_if(Auth::user()->isAdmin(), 403);
        $packed = $this->packed((int) Auth::id(), $finance);

        return view('seller.earnings', $packed);
    }

    public function export(FinanceBook $finance): StreamedResponse
    {
        abort_if(Auth::user()->isAdmin(), 403);
        $sales = $this->packed((int) Auth::id(), $finance)['sales'];

        return response()->streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Thời điểm', 'Đơn', 'Sản phẩm', 'Doanh thu', 'Phí sàn', 'Tiền về shop', 'Trạng thái']);
            foreach ($sales as $row) {
                fputcsv($out, [
                    $row->when?->format('Y-m-d H:i'),
                    $row->code,
                    $row->title,
                    $row->gross,
                    $row->fee,
                    $row->net,
                    $row->settled ? 'Đã về tài khoản' : 'Chờ người mua xác nhận',
                ]);
            }
            fclose($out);
        }, 'thu-chi-shop.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function packed(int $sellerId, FinanceBook $finance): array
    {
        $items = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', fn ($query) => $query->whereIn('status', ['completed', 'paid', 'cod_ordered', 'cod_paid']))
            ->with(['order:id,code,status,escrow_status,released_at,created_at'])
            ->latest('id')
            ->get();
        $report = $finance->sellerReport(
            $sellerId,
            $items->pluck('order_id')->unique()->map(fn ($id) => (int) $id)->all(),
            14
        );

        return [
            'account' => $report['account'],
            'sales' => $this->sales($items, collect($report['entries'])),
            'series' => $report['series'],
            'income' => $report['income'],
            'expense' => $report['expense'],
        ];
    }

    private function sales(Collection $items, Collection $entries): Collection
    {
        $ledger = $entries->groupBy('order_id');

        $grossByOrder = $items->groupBy('order_id')->map(
            fn ($rows) => (int) $rows->sum(fn ($row) => (int) round($row->price * $row->quantity))
        );

        return $items->map(function (OrderItem $item) use ($ledger, $grossByOrder) {
            $gross = (int) round($item->price * $item->quantity);
            $orderGross = max(1, (int) $grossByOrder[$item->order_id]);
            $entries = $ledger->get($item->order_id, collect());
            $settled = $entries->isNotEmpty();
            $share = $gross / $orderGross;
            $fee = $settled
                ? (int) round((int) $entries->where('type', LedgerEntry::PLATFORM_FEE)->sum('amount') * $share)
                : (int) round($gross * WalletService::COMMISSION_RATE);

            return (object) [
                'title' => $item->title,
                'code' => $item->order->code,
                'when' => $item->order->released_at ?? $item->order->created_at,
                'gross' => $gross,
                'fee' => $fee,
                'net' => $settled
                    ? (int) round((int) $entries->where('type', LedgerEntry::SELLER_PAYOUT)->sum('amount') * $share)
                    : max(0, $gross - $fee),
                'settled' => $settled,
            ];
        });
    }
}
