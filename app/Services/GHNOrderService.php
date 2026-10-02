<?php

namespace App\Services;

use App\Models\Order;

class GHNOrderService
{
    public function __construct(private GHNService $ghn)
    {
    }

    public function create(Order $order, bool $isPaid = false): array
    {
        $items = [];
        $weight = 0;
        $goodsValue = 0;

        foreach ($order->items as $item) {
            $itemWeight = (int) ($item->listing->weight ?? $this->ghn->productWeight());
            $weight += $itemWeight * (int) $item->quantity;
            $goodsValue += (int) $item->price * (int) $item->quantity;
            $items[] = [
                'name' => $item->title ?: 'Thiet bi da qua su dung',
                'quantity' => (int) $item->quantity,
                'price' => (int) $item->price,
                'weight' => $itemWeight,
            ];
        }

        // MoMo đã thu tiền hàng + phí ship: shop trả phí, không thu hộ.
        // COD: người nhận trả phí và thu hộ tổng đơn.
        return $this->ghn->createOrder([
            'payment_type_id' => $isPaid ? 1 : 2,
            'note' => $isPaid
                ? 'Relic '.$order->code.' - Da thanh toan MoMo (khong thu COD)'
                : 'Relic '.$order->code.' - COD',
            'required_note' => 'KHONGCHOXEMHANG',
            'to_name' => $order->name,
            'to_phone' => $order->phone,
            'to_address' => $order->address,
            'to_ward_code' => (string) $order->to_ward_code,
            'to_district_id' => (int) $order->to_district_id,
            'cod_amount' => $isPaid ? 0 : (int) $order->total_price,
            'weight' => $weight > 0 ? $weight : 200,
            'length' => 15,
            'width' => 15,
            'height' => 10,
            'service_type_id' => 2,
            'insurance_value' => min(5000000, max(0, $goodsValue)),
            'items' => $items,
        ]);
    }
}
