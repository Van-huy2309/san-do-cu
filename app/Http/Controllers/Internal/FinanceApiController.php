<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\FinanceStore;
use Illuminate\Http\Request;

class FinanceApiController extends Controller
{
    public function __construct(private FinanceStore $store) {}

    public function hasBank(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->ok(['exists' => $this->store->hasBank((int) $data['user_id'])]);
    }

    public function showBank(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->ok([
            'account' => $this->store->bankFor((int) $data['user_id'])?->toArray(),
        ]);
    }

    public function saveBank(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'bank_name' => ['required', 'string', 'max:80'],
            'account_holder' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'regex:/^\d{6,19}$/'],
        ]);
        $account = $this->store->saveBank(
            (int) $data['user_id'],
            $data['bank_name'],
            $data['account_holder'],
            $data['account_number']
        );

        return $this->ok(['account' => $account->toArray()]);
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:0'],
        ]);
        $this->store->receive((int) $data['order_id'], (int) $data['amount']);

        return $this->ok();
    }

    public function refund(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:0'],
        ]);
        $this->store->refund((int) $data['order_id'], (int) $data['amount']);

        return $this->ok();
    }

    public function settle(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'merchandise' => ['required', 'integer', 'min:0'],
            'rows' => ['present', 'array', 'max:50'],
            'rows.*.seller_id' => ['required', 'integer', 'min:1'],
            'rows.*.fee' => ['required', 'integer', 'min:0'],
            'rows.*.net' => ['required', 'integer', 'min:0'],
        ]);
        $this->store->settle((int) $data['order_id'], (int) $data['merchandise'], $data['rows']);

        return $this->ok();
    }

    public function seller(Request $request)
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'min:1'],
            'order_ids' => ['present', 'array', 'max:500'],
            'order_ids.*' => ['integer', 'min:1'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $report = $this->store->sellerReport(
            (int) $data['seller_id'],
            array_map('intval', $data['order_ids']),
            (int) $data['days']
        );

        return $this->ok([
            'income' => $report['income'],
            'expense' => $report['expense'],
            'series' => $report['series'],
            'entries' => array_map(fn ($row) => $row->toArray(), $report['entries']),
            'account' => $report['account']?->toArray(),
        ]);
    }

    public function admin(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'limit' => ['required', 'integer', 'min:0', 'max:5000'],
        ]);
        $report = $this->store->adminReport((int) $data['days'], (int) $data['limit']);

        return $this->ok([
            'totals' => $report['totals'],
            'series' => $report['series'],
            'entries' => array_map(fn ($row) => $row->toArray(), $report['entries']),
            'accounts' => array_map(fn ($row) => $row->toArray(), $report['accounts']),
        ]);
    }

    private function ok(array $extra = [])
    {
        return response()->json(['ok' => true] + $extra);
    }
}
