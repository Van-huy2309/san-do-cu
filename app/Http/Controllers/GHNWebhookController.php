<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderShippingStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GHNWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('GHN webhook received', $request->except(['token']));

        $orderCode = $request->input('OrderCode') ?? $request->input('order_code');
        $status = $request->input('Status') ?? $request->input('status');

        if ($orderCode && $status) {
            $mapped = OrderShippingStatus::fromGhn((string) $status);
            $order = Order::where('ghn_order_code', $orderCode)->first();

            if ($order) {
                $payload = ['shipping_status' => $mapped];

                if ($mapped === OrderShippingStatus::DELIVERED
                    && in_array($order->status, ['cod_ordered', 'pending'], true)) {
                    $payload['status'] = 'cod_paid';
                }

                if ($mapped === OrderShippingStatus::CANCELLED
                    && ! in_array($order->status, ['paid', 'completed', 'refunded'], true)
                    && ! in_array($order->escrow_status, ['held', 'released'], true)) {
                    $payload['status'] = 'cancelled';
                }

                $order->update($payload);
            }
        }

        return response()->json(['message' => 'Received']);
    }
}
