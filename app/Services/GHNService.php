<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GHNService
{
    protected string $baseUrl;

    protected string $token;

    protected int $shopId;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.ghn.base_url');
        $this->token = (string) (config('services.ghn.token') ?? '');
        $this->shopId = (int) config('services.ghn.shop_id', 0);
    }

    public function productWeight(): int
    {
        return (int) config('services.ghn.default_weight', 400);
    }

    public function packageParameters(int $weight): array
    {
        return [
            'service_type_id' => 2,
            'weight' => max($weight, $this->productWeight()),
            'length' => 20,
            'width' => 15,
            'height' => 10,
            'insurance_value' => 0,
        ];
    }

    protected function client(bool $withShop = false)
    {
        $headers = [
            'Token' => $this->token,
            'Content-Type' => 'application/json',
        ];

        if ($withShop && $this->shopId > 0) {
            $headers['ShopId'] = (string) $this->shopId;
        }

        return Http::baseUrl($this->baseUrl)
            ->withOptions([
                'verify' => filter_var(config('services.ghn.verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
            ])
            ->acceptJson()
            ->timeout(12)
            ->withHeaders($headers);
    }

    public function useShop(int $shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    public function shopId(): int
    {
        return $this->shopId;
    }

    public function listShops(): array
    {
        return $this->post('/v2/shop/all', [
            'offset' => 0,
            'limit' => 50,
            'client_phone' => '',
        ], false);
    }

    public function registerShop(array $shop): array
    {
        return $this->post('/v2/shop/register', $shop, false);
    }

    public function updateShop(array $shop): array
    {
        return $this->post('/v2/shop/update', $shop, true);
    }

    public function getProvinces(): array
    {
        return $this->rememberMaster('ghn.provinces', fn () => $this->get('/master-data/province'));
    }

    public function getDistricts(int $provinceId): array
    {
        return $this->rememberMaster("ghn.districts.{$provinceId}", fn () => $this->post('/master-data/district', [
            'province_id' => $provinceId,
        ]));
    }

    public function getWards(int $districtId): array
    {
        return $this->rememberMaster("ghn.wards.{$districtId}", fn () => $this->post('/master-data/ward', [
            'district_id' => $districtId,
        ]));
    }

    protected function rememberMaster(string $key, callable $loader): array
    {
        $cached = Cache::store('file')->get($key);
        if (is_array($cached) && ! empty($cached['data'])) {
            return $cached;
        }

        $result = $loader();
        if (! empty($result['data'])) {
            Cache::store('file')->put($key, $result, 43200);
        }

        return $result;
    }

    public function pickup(): array
    {
        $cached = Cache::store('file')->get('ghn.pickup.v2');
        if (is_array($cached) && ! empty($cached['shop_id']) && ! empty($cached['from_ward_code'])) {
            $this->shopId = (int) $cached['shop_id'];

            return $cached;
        }

        $this->resolveShopId();
        $district = (int) config('services.ghn.from_district_id');
        $ward = (string) config('services.ghn.from_ward_code', '');
        if ($ward === '') {
            $ward = $this->firstPickableWard($district);
        }

        $pickup = [
            'shop_id' => $this->shopId,
            'from_district_id' => $district,
            'from_ward_code' => $ward,
            'from_name' => (string) config('services.ghn.from_name', 'Relic'),
            'from_phone' => (string) config('services.ghn.from_phone', '0367828305'),
            'from_address' => (string) config('services.ghn.from_address', '12 Nguyen Chi Thanh'),
        ];
        Cache::store('file')->put('ghn.pickup.v2', $pickup, 3600);

        return $pickup;
    }

    protected function resolveShopId(): void
    {
        $shops = $this->listShops()['data']['shops'] ?? [];
        if ($shops === []) {
            return;
        }
        $ids = array_map(fn ($shop) => (int) ($shop['_id'] ?? 0), $shops);
        if (! in_array($this->shopId, $ids, true)) {
            $this->shopId = $ids[0];
            Log::warning('GHN shop_id trong .env là client_id; đã chuyển sang shop thật', [
                'shop_id' => $this->shopId,
            ]);
        }
    }

    protected function firstPickableWard(int $districtId): string
    {
        foreach ($this->getWards($districtId)['data'] ?? [] as $ward) {
            if ((int) ($ward['Status'] ?? 0) === 1 && (int) ($ward['PickType'] ?? 0) > 0) {
                return (string) ($ward['WardCode'] ?? '');
            }
        }
        $first = $this->getWards($districtId)['data'][0] ?? [];

        return (string) ($first['WardCode'] ?? $first['ward_code'] ?? '');
    }

    public function calculateFee(array $params): array
    {
        $pickup = $this->pickup();

        return $this->post('/v2/shipping-order/fee', array_merge($pickup, $params), true);
    }

    public function createOrder(array $orderData): array
    {
        $pickup = $this->pickup();

        return $this->post('/v2/shipping-order/create', array_merge($pickup, $orderData), true);
    }

    public function cancelOrder(array $orderCodes): array
    {
        return $this->post('/v2/switch-status/cancel', [
            'order_codes' => $orderCodes,
            'shop_id' => $this->shopId,
        ], true);
    }

    protected function get(string $uri, array $query = [], bool $withShop = false): array
    {
        try {
            $response = $this->client($withShop)->get($uri, $query);

            return $this->decode($response, 'GET', $uri);
        } catch (ConnectionException $exception) {
            Log::error('Unable to connect to GHN', ['uri' => $uri, 'error' => $exception->getMessage()]);

            return ['code' => -1, 'message' => 'Unable to connect to GHN.'];
        }
    }

    protected function post(string $uri, array $payload, bool $withShop = false): array
    {
        try {
            $response = $this->client($withShop)->post($uri, $payload);

            return $this->decode($response, 'POST', $uri);
        } catch (ConnectionException $exception) {
            Log::error('Unable to connect to GHN', ['uri' => $uri, 'error' => $exception->getMessage()]);

            return ['code' => -1, 'message' => 'Unable to connect to GHN.'];
        }
    }

    protected function decode($response, string $method, string $uri): array
    {
        $json = $response->json();

        if (! $response->successful() || empty($json['data'])) {
            Log::warning("GHN {$method} request failed", [
                'uri' => $uri,
                'status' => $response->status(),
                'body' => $json,
            ]);
        }

        return $json ?? ['code' => $response->status(), 'message' => 'GHN API request failed.'];
    }
}
