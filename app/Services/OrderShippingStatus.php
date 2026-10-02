<?php

namespace App\Services;

class OrderShippingStatus
{
    public const PENDING = 'pending';

    public const READY = 'ready_to_pick';

    public const PICKING = 'picking';

    public const DELIVERING = 'delivering';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const RETURN = 'return';

    public const RETURNING = 'returning';

    public const RETURNED = 'returned';

    public const DELETABLE = [
        self::PENDING,
        'not_shipped',
        'processing',
        self::READY,
        self::PICKING,
    ];

    public const LOCKED = [
        self::DELIVERING,
        'picked',
        'storing',
        'transporting',
        'sorting',
        self::DELIVERED,
        self::RETURN,
        self::RETURNING,
        self::RETURNED,
        'return_transporting',
        'return_sorting',
        self::CANCELLED,
    ];

    public static function labels(): array
    {
        return [
            'pending' => 'Chờ tạo vận đơn',
            'not_shipped' => 'Chưa giao hàng',
            'processing' => 'Đang tạo vận đơn',
            'ready_to_pick' => 'Chờ lấy hàng',
            'picking' => 'Đang lấy hàng',
            'picked' => 'Đã lấy hàng',
            'storing' => 'Đang lưu kho',
            'transporting' => 'Đang trung chuyển',
            'sorting' => 'Đang phân loại',
            'delivering' => 'Đang giao hàng',
            'delivered' => 'Giao hàng thành công',
            'return' => 'Chờ hoàn hàng',
            'returning' => 'Đang hoàn hàng',
            'returned' => 'Đã hoàn hàng',
            'return_transporting' => 'Đang chuyển hoàn',
            'return_sorting' => 'Đang phân loại hoàn',
            'cancelled' => 'Đã hủy',
        ];
    }

    public static function fromGhn(string $ghnStatus): string
    {
        $status = strtolower(trim($ghnStatus));

        return match (true) {
            in_array($status, ['delivered'], true) => self::DELIVERED,
            in_array($status, [
                'delivery_fail', 'undeliverable', 'exception', 'cancel', 'cancelled',
                'lost', 'damage', 'destroy',
            ], true) => self::CANCELLED,
            in_array($status, [
                'return', 'returning', 'returned', 'return_transporting', 'return_sorting', 'waiting_to_return',
            ], true) => match ($status) {
                'returning', 'return_transporting', 'return_sorting' => self::RETURNING,
                'returned' => self::RETURNED,
                default => self::RETURN,
            },
            in_array($status, [
                'picked', 'storing', 'transporting', 'sorting', 'delivering', 'money_collect_picking', 'money_collect_delivering',
            ], true) => self::DELIVERING,
            in_array($status, ['picking', 'ready_to_pick'], true) => $status === 'picking' ? self::PICKING : self::READY,
            default => $status,
        };
    }

    public static function canDelete(?string $shippingStatus): bool
    {
        return in_array($shippingStatus, self::DELETABLE, true);
    }

    public static function canCancel(?string $shippingStatus): bool
    {
        return self::canDelete($shippingStatus);
    }
}
