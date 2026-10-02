<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    public function enabled(): bool
    {
        return filled(config('services.elasticsearch.host'));
    }

    public function indexListing(Listing $listing): void
    {
        if (! $this->enabled()) {
            return;
        }

        $listing->loadMissing(['brand', 'category']);
        $this->request('PUT', '/relic_listings/_doc/' . $listing->id, [
            'id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $listing->title,
            'description' => $listing->description,
            'model' => $listing->model,
            'brand' => $listing->brand->name ?? '',
            'category' => $listing->category->name ?? '',
            'city' => $listing->city,
            'areas' => $listing->areaList(),
            'status' => $listing->status,
            'price' => (int) $listing->price,
            'condition' => $listing->condition,
            'verified' => $listing->isOriginVerified(),
            'location' => ($listing->lat && $listing->lng)
                ? ['lat' => (float) $listing->lat, 'lon' => (float) $listing->lng]
                : null,
        ]);
    }

    public function deleteListing(int $id): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->request('DELETE', '/relic_listings/_doc/' . $id);
    }

    /**
     * @return list<int>
     */
    public function searchIds(string $q, array $filters = [], int $size = 200): array
    {
        if (! $this->enabled() || $q === '') {
            return [];
        }

        $must = [
            [
                'multi_match' => [
                    'query' => $q,
                    'fields' => ['title^3', 'model^2', 'brand^2', 'category', 'description', 'city'],
                    'operator' => 'and',
                    'fuzziness' => 'AUTO',
                ],
            ],
        ];
        $filter = [['term' => ['status' => 'active']]];
        if (! empty($filters['category_name'])) {
            $filter[] = ['match' => ['category' => $filters['category_name']]];
        }
        if (! empty($filters['verified'])) {
            $filter[] = ['term' => ['verified' => true]];
        }
        if (! empty($filters['lat']) && ! empty($filters['lng']) && ! empty($filters['radius'])) {
            $filter[] = [
                'geo_distance' => [
                    'distance' => ((float) $filters['radius']) . 'km',
                    'location' => ['lat' => (float) $filters['lat'], 'lon' => (float) $filters['lng']],
                ],
            ];
        }

        $json = $this->request('POST', '/relic_listings/_search', [
            'size' => $size,
            'query' => ['bool' => ['must' => $must, 'filter' => $filter]],
            '_source' => false,
        ]);

        $hits = $json['hits']['hits'] ?? [];

        return array_map(fn ($hit) => (int) $hit['_id'], $hits);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim((string) config('services.elasticsearch.host'), '/') . $path;
        $pending = Http::timeout(4)->acceptJson();
        $user = config('services.elasticsearch.user');
        if ($user) {
            $pending = $pending->withBasicAuth($user, (string) config('services.elasticsearch.password'));
        }
        $response = $body === null
            ? $pending->send($method, $url)
            : $pending->send($method, $url, ['json' => $body]);

        if ($response->failed()) {
            Log::warning('Elasticsearch request failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Elasticsearch unavailable');
        }

        return $response->json() ?? [];
    }
}
