<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function disk(): string
    {
        $s3 = config('filesystems.disks.s3');
        if (! empty($s3['bucket']) && ! empty($s3['key']) && ! empty($s3['secret'])) {
            return 's3';
        }

        return 'public';
    }

    public function storePublic(UploadedFile $file, string $folder): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = Str::random(24) . '.' . $ext;
        $disk = $this->disk();
        $path = $file->storeAs($folder, $name, $disk);

        return $disk === 's3' ? 's3:' . $path : 'storage/' . $path;
    }

    public function url(?string $path): string
    {
        if (! $path) {
            return url('images/placeholder.svg');
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, 's3:')) {
            return Storage::disk('s3')->url(substr($path, 3));
        }
        if (str_starts_with($path, 'storage/')) {
            return url($path);
        }

        return url($path);
    }
}
