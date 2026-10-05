<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Collection;

class VoucherService
{
    /**
     * @param  Collection<int, mixed>|array<int, array<string, mixed>>  $cart
     * @return array{voucher: Voucher, discount: int, eligible: int}
     */
    public function quote(string $code, Collection|array $cart): array
    {
        $voucher = Voucher::query()->where('code', $this->normalize($code))->first();
        if (! $voucher) {
            throw new \RuntimeException('Mã giảm giá không tồn tại.');
        }
        $this->assertOpen($voucher);
        $eligible = $this->eligibleAmount($voucher, $cart);
        if ($eligible <= 0) {
            throw new \RuntimeException('Mã này chỉ áp dụng cho sản phẩm của shop đã tạo phiếu.');
        }
        if ($eligible < (int) $voucher->min_order) {
            throw new \RuntimeException('Đơn chưa đạt mức tối thiểu '.number_format((int) $voucher->min_order, 0, ',', '.').' ₫ của phiếu.');
        }

        return [
            'voucher' => $voucher,
            'discount' => $this->discountAmount($voucher, $eligible),
            'eligible' => $eligible,
        ];
    }

    /**
     * @param  Collection<int, mixed>|array<int, array<string, mixed>>  $cart
     */
    public function redeem(string $code, Collection|array $cart, User $user, Order $order): int
    {
        $voucher = Voucher::query()->where('code', $this->normalize($code))->lockForUpdate()->first();
        if (! $voucher) {
            throw new \RuntimeException('Mã giảm giá không tồn tại.');
        }
        $this->assertOpen($voucher);
        $eligible = $this->eligibleAmount($voucher, $cart);
        if ($eligible <= 0) {
            throw new \RuntimeException('Mã này chỉ áp dụng cho sản phẩm của shop đã tạo phiếu.');
        }
        if ($eligible < (int) $voucher->min_order) {
            throw new \RuntimeException('Đơn chưa đạt mức tối thiểu của phiếu.');
        }

        $discount = $this->discountAmount($voucher, $eligible);
        $voucher->increment('used_count');
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => $discount,
        ]);

        return $discount;
    }

    public function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function assertOpen(Voucher $voucher): void
    {
        if (! $voucher->is_active) {
            throw new \RuntimeException('Phiếu giảm giá đã tắt.');
        }
        if ($voucher->ends_at && $voucher->ends_at->isPast()) {
            throw new \RuntimeException('Phiếu giảm giá đã hết hạn.');
        }
        if ($voucher->remaining() < 1) {
            throw new \RuntimeException('Phiếu giảm giá đã hết số lượng.');
        }
    }

    /**
     * @param  Collection<int, mixed>|array<int, array<string, mixed>>  $cart
     */
    private function eligibleAmount(Voucher $voucher, Collection|array $cart): int
    {
        $items = $cart instanceof Collection ? $cart : collect($cart);

        return (int) $items->sum(function ($item) use ($voucher) {
            if ($voucher->seller_id !== null && (int) ($item['seller_id'] ?? 0) !== (int) $voucher->seller_id) {
                return 0;
            }

            return (int) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
        });
    }

    private function discountAmount(Voucher $voucher, int $eligible): int
    {
        if ($voucher->discount_type === 'percent') {
            $percent = min(100, max(0, (int) $voucher->discount_value));

            return (int) round($eligible * $percent / 100);
        }

        return min($eligible, max(0, (int) $voucher->discount_value));
    }
};
