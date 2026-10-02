<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class MomoService
{
    public function payableAmount(Order $order): int
    {
        $type = (string) config('services.momo.request_type', 'payWithATM');
        $min = $type === 'captureWallet' ? 1000 : 10000;

        return max($min, (int) $order->total_price);
    }

    public function createPayment(Order $order, PaymentTransaction $transaction): array
    {
        $data = $this->buildCreatePayload($order, $transaction);

        $transaction->update([
            'gateway_order_id' => $data['orderId'],
            'amount' => (int) $data['amount'],
            'request_payload' => $data,
        ]);

        $result = $this->postCreate($data);

        $transaction->update([
            'response_payload' => $result,
            'result_code' => isset($result['resultCode']) ? (int) $result['resultCode'] : null,
            'message' => $result['message'] ?? null,
            'status' => isset($result['payUrl']) ? 'initiated' : 'failed',
        ]);

        return $result;
    }

    public function pingCreate(int $amount = 10000): array
    {
        $order = new Order();
        $order->id = 0;
        $order->total_price = $amount;
        $transaction = new PaymentTransaction();
        $transaction->id = 0;

        return $this->postCreate($this->buildCreatePayload($order, $transaction));
    }

    public function buildCreatePayload(Order $order, PaymentTransaction $transaction): array
    {
        $partnerCode = config('services.momo.partner_code', '');
        $accessKey = config('services.momo.access_key', '');
        $secretKey = config('services.momo.secret_key', '');
        $orderInfo = 'Thanh toan don hang #' . $order->id;
        $amount = (string) $this->payableAmount($order);
        $orderId = $order->id . '_' . $transaction->id . '_' . str_replace('.', '', uniqid('', true));
        $redirectUrl = $this->notifyUrl('user.payment.momo.callback', config('services.momo.redirect_url'));
        $ipnUrl = $this->notifyUrl('payment.momo.ipn', config('services.momo.ipn_url'));
        $extraData = (string) $order->id;
        $requestId = (string) time();
        $requestType = (string) config('services.momo.request_type', 'payWithATM');

        $rawHash = 'accessKey=' . $accessKey
            . '&amount=' . $amount
            . '&extraData=' . $extraData
            . '&ipnUrl=' . $ipnUrl
            . '&orderId=' . $orderId
            . '&orderInfo=' . $orderInfo
            . '&partnerCode=' . $partnerCode
            . '&redirectUrl=' . $redirectUrl
            . '&requestId=' . $requestId
            . '&requestType=' . $requestType;

        return [
            'partnerCode' => $partnerCode,
            'partnerName' => config('services.momo.partner_name', 'Relic'),
            'storeId' => 'MomoStore',
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => hash_hmac('sha256', $rawHash, $secretKey),
        ];
    }

    private function postCreate(array $data): array
    {
        $endpoint = config('services.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');
        $response = Http::withOptions([
            'verify' => filter_var(config('services.momo.verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
        ])->timeout(20)->post($endpoint, $data);

        return $response->json() ?? [];
    }

    public function isSuccessful(array $payload): bool
    {
        return (string) ($payload['resultCode'] ?? '') === '0';
    }

    public function markPaid(PaymentTransaction $transaction, array $payload): void
    {
        $transaction->update([
            'transaction_id' => $payload['transId'] ?? null,
            'result_code' => (int) ($payload['resultCode'] ?? 0),
            'message' => $payload['message'] ?? null,
            'response_payload' => $payload,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);
    }

    public function markFailed(PaymentTransaction $transaction, array $payload): void
    {
        $transaction->update([
            'transaction_id' => $payload['transId'] ?? null,
            'result_code' => isset($payload['resultCode']) ? (int) $payload['resultCode'] : null,
            'message' => $payload['message'] ?? null,
            'response_payload' => $payload,
            'status' => 'failed',
        ]);
    }

    public function isValidSuccessfulResponse(array $payload): bool
    {
        return $this->isValidResponse($payload) && $this->isSuccessful($payload);
    }

    public function isValidResponse(array $payload): bool
    {
        if (! isset($payload['signature'])) {
            return false;
        }

        $accessKey = config('services.momo.access_key', '');
        $secretKey = config('services.momo.secret_key', '');

        $rawHash = 'accessKey=' . $accessKey
            . '&amount=' . ($payload['amount'] ?? '')
            . '&extraData=' . ($payload['extraData'] ?? '')
            . '&message=' . ($payload['message'] ?? '')
            . '&orderId=' . ($payload['orderId'] ?? '')
            . '&orderInfo=' . ($payload['orderInfo'] ?? '')
            . '&orderType=' . ($payload['orderType'] ?? '')
            . '&partnerCode=' . ($payload['partnerCode'] ?? '')
            . '&payType=' . ($payload['payType'] ?? '')
            . '&requestId=' . ($payload['requestId'] ?? '')
            . '&responseTime=' . ($payload['responseTime'] ?? '')
            . '&resultCode=' . ($payload['resultCode'] ?? '')
            . '&transId=' . ($payload['transId'] ?? '');

        return hash_equals(
            hash_hmac('sha256', $rawHash, $secretKey),
            (string) $payload['signature']
        );
    }

    public function orderId(array $payload): ?int
    {
        $orderId = $payload['extraData'] ?? null;

        return is_numeric($orderId) ? (int) $orderId : null;
    }

    private function notifyUrl(string $routeName, mixed $configured): string
    {
        if (is_string($configured) && $configured !== '') {
            return $this->asciiUrl($configured);
        }

        return $this->asciiUrl(route($routeName));
    }

    private function asciiUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return $url;
        }
        $segments = array_map(
            static fn (string $segment) => $segment === '' ? '' : rawurlencode(rawurldecode($segment)),
            explode('/', $parts['path'])
        );

        return $parts['scheme']
            .'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .implode('/', $segments)
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
