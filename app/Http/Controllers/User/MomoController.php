<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\GHNOrderService;
use App\Services\MomoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MomoController extends Controller
{
    public function start(Order $order, MomoService $momo)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (in_array($order->status, ['paid', 'completed', 'cod_ordered'], true)) {
            return redirect()->route('user.orders.show', $order)
                ->with('success', 'Đơn hàng đã được thanh toán.');
        }

        return $this->redirectToMomo($order, $this->pendingTransaction($order), $momo);
    }

    public function payAgain(Order $order, MomoService $momo)
    {
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        abort_unless($order->canPayAgain(), 403);

        return $this->redirectToMomo($order, $this->newTransaction($order), $momo);
    }

    public function callback(Request $request, GHNOrderService $ghnOrders, MomoService $momo)
    {
        Log::info('MoMo callback received', [
            'payload' => $request->except('signature'),
            'has_signature' => $request->has('signature'),
        ]);

        if (! $momo->isValidSuccessfulResponse($request->all())) {
            Log::warning('MoMo callback rejected', [
                'result_code' => $request->input('resultCode'),
                'order_id' => $request->input('orderId'),
                'signature_valid' => $momo->isValidResponse($request->all()),
            ]);

            if ($momo->isValidResponse($request->all())) {
                $this->markFailed($request->all(), $momo);
            }

            return redirect()->route('user.orders.index')->with('error', 'Giao dịch MoMo thất bại.');
        }

        $result = $this->completePayment($request->all(), $ghnOrders, $momo);

        if ($result === 'invalid') {
            return redirect()->route('user.orders.index')->with('error', 'Giao dịch MoMo không khớp đơn hàng.');
        }

        if ($result === 'processing') {
            return redirect()->route('user.orders.index')->with('success', 'Đơn hàng đang được xử lý.');
        }

        $message = in_array($result, ['created', 'already_created'], true)
            ? 'Thanh toán MoMo thành công! Vận đơn GHN đã được khởi tạo.'
            : 'Thanh toán thành công! Đơn hàng đang chờ tạo vận đơn GHN.';

        return redirect()->route('user.orders.index')->with('success', $message);
    }

    public function ipn(Request $request, GHNOrderService $ghnOrders, MomoService $momo)
    {
        Log::info('MoMo IPN received', [
            'payload' => $request->except('signature'),
            'has_signature' => $request->has('signature'),
        ]);

        if ($momo->isValidSuccessfulResponse($request->all())) {
            $this->completePayment($request->all(), $ghnOrders, $momo);
        } elseif ($momo->isValidResponse($request->all())) {
            $this->markFailed($request->all(), $momo);
        }

        return response()->json(['message' => 'Received']);
    }

    private function pendingTransaction(Order $order): PaymentTransaction
    {
        $existing = $order->paymentTransactions()
            ->where('gateway', 'momo')
            ->whereIn('status', ['pending', 'initiated', 'failed'])
            ->latest('id')
            ->first();

        return $existing ?? $this->newTransaction($order);
    }

    private function newTransaction(Order $order): PaymentTransaction
    {
        return PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'momo',
            'amount' => $order->total_price,
            'status' => 'pending',
        ]);
    }

    private function redirectToMomo(Order $order, PaymentTransaction $transaction, MomoService $momo)
    {
        $payUrl = data_get($transaction->response_payload, 'payUrl');
        if ($transaction->status === 'initiated' && is_string($payUrl) && $payUrl !== '') {
            return redirect($payUrl);
        }

        $result = $momo->createPayment($order, $transaction);

        return isset($result['payUrl'])
            ? redirect($result['payUrl'])
            : redirect()->route('user.orders.index')->with('error', 'Không thể kết nối tới MoMo.');
    }

    private function completePayment(array $payload, GHNOrderService $ghnOrders, MomoService $momo): string
    {
        $result = DB::transaction(function () use ($payload, $momo) {
            $transaction = PaymentTransaction::where('gateway', 'momo')
                ->where('gateway_order_id', $payload['orderId'] ?? '')
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                return 'invalid';
            }

            $order = Order::lockForUpdate()->find($transaction->order_id);
            if (! $order) {
                return 'invalid';
            }

            if ($transaction->status === 'paid' || $order->status === 'paid') {
                if ($order->ghn_order_code) {
                    return 'already_created';
                }

                return ['create', $order->id];
            }

            if ($order->ghn_order_code) {
                return 'already_created';
            }

            if ($order->shipping_status === 'processing') {
                return 'processing';
            }

            if ((int) $transaction->amount !== (int) ($payload['amount'] ?? 0)) {
                $momo->markFailed($transaction, $payload);

                return 'invalid';
            }

            $order->update(['status' => 'paid', 'shipping_status' => 'processing']);
            $momo->markPaid($transaction, $payload);
            $order->load('items');
            app(\App\Services\EscrowService::class)->holdPaidOrder($order);

            return ['create', $order->id];
        });

        if (! is_array($result)) {
            return (string) $result;
        }

        $order = Order::with('items.listing')->find($result[1]);
        $response = $ghnOrders->create($order, true);

        if (isset($response['code']) && $response['code'] === 200) {
            $order->update([
                'ghn_order_code' => $response['data']['order_code'],
                'shipping_status' => 'ready_to_pick',
            ]);

            return 'created';
        }

        Log::error('GHN order failed after MoMo payment', [
            'order_id' => $order->id,
            'response' => $response,
        ]);

        $order->update(['shipping_status' => 'ready_to_pick']);

        return 'failed';
    }

    private function markFailed(array $payload, MomoService $momo): void
    {
        $transaction = PaymentTransaction::where('gateway', 'momo')
            ->where('gateway_order_id', $payload['orderId'] ?? '')
            ->first();

        if ($transaction && $transaction->status !== 'paid') {
            $momo->markFailed($transaction, $payload);
            $order = $transaction->order;
            if ($order && $order->status !== 'paid' && ! $order->ghn_order_code) {
                $order->update([
                    'status' => 'pending',
                    'shipping_status' => 'pending',
                ]);
            }
        }
    }
}
