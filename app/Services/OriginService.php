<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingOrigin;
use Illuminate\Support\Str;

class OriginService
{
    public function upsert(Listing $listing, array $data, ?string $invoicePath = null, ?string $boxPath = null): ListingOrigin
    {
        $serial = $this->normalize($data['serial'] ?? '');
        $imei = $this->normalize($data['imei'] ?? '');

        $payload = [
            'serial_hash' => $serial !== '' ? hash('sha256', $serial) : null,
            'serial_last4' => $serial !== '' ? Str::substr($serial, -4) : null,
            'imei_last4' => $imei !== '' ? Str::substr($imei, -4) : null,
            'purchase_channel' => $data['purchase_channel'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'status' => 'pending',
            'verified_at' => null,
            'verified_by' => null,
        ];

        if ($invoicePath) {
            $payload['invoice_path'] = $invoicePath;
        }
        if ($boxPath) {
            $payload['box_photo_path'] = $boxPath;
        }

        $origin = $listing->origin;
        if ($origin) {
            $origin->update($payload);

            return $origin->refresh();
        }

        $payload['listing_id'] = $listing->id;
        $payload['seal_code'] = $this->makeSeal($listing, $serial);

        return ListingOrigin::create($payload);
    }

    public function makeSeal(Listing $listing, string $serial): string
    {
        $raw = $listing->id . '|' . $serial . '|' . config('app.key');

        return 'RLC-' . strtoupper(substr(hash('sha256', $raw), 0, 12));
    }

    private function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value) ?? '');
    }
}
