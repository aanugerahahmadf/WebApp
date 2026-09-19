<?php

namespace App\Services\StorageService;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public static function disk(): string
    {
        return env('FILESYSTEM_DISK', 'local');
    }

    public static function upload(UploadedFile $file, string $directory = 'uploads', ?string $disk = null): string
    {
        $disk = $disk ?? self::disk();

        if ($disk === 'cloudinary') {
            return self::uploadToCloudinary($file, $directory);
        }

        return $file->store($directory, $disk);
    }

    public static function uploadWithCustomName(UploadedFile $file, string $directory, string $fileName, ?string $disk = null): string
    {
        $disk = $disk ?? self::disk();

        if ($disk === 'cloudinary') {
            return self::uploadToCloudinary($file, $directory);
        }

        return $file->storeAs($directory, $fileName, $disk);
    }

    public static function uploadMultiple(array $files, string $directory = 'uploads', ?string $disk = null): array
    {
        return array_map(fn ($file) => self::upload($file, $directory, $disk), $files);
    }

    public static function delete(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?? self::disk();

        if ($disk === 'cloudinary') {
            return self::deleteFromCloudinary($path);
        }

        return Storage::disk($disk)->delete($path);
    }

    public static function url(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? self::disk();

        if ($disk === 'cloudinary') {
            return $path;
        }

        return Storage::disk($disk)->url($path);
    }

    public static function path(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? self::disk();

        if ($disk === 'cloudinary') {
            return $path;
        }

        return Storage::disk($disk)->path($path);
    }

    private static function uploadToCloudinary(UploadedFile $file, string $directory): string
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp = now()->timestamp;
        $publicId = $directory . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();

        $paramsToSign = [
            'timestamp' => $timestamp,
            'public_id' => $publicId,
        ];

        ksort($paramsToSign);
        $signature = hash('sha1', http_build_query($paramsToSign) . $apiSecret);

        $response = Http::attach(
            'file', $file->getContent(), $file->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'public_id' => $publicId,
            'signature' => $signature,
            'folder' => $directory,
        ]);

        if ($response->successful()) {
            return $response->json('secure_url');
        }

        throw new \RuntimeException('Cloudinary upload failed: ' . $response->body());
    }

    private static function deleteFromCloudinary(string $publicId): bool
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        $timestamp = now()->timestamp;
        $paramsToSign = ['timestamp' => $timestamp, 'public_id' => $publicId];
        ksort($paramsToSign);
        $signature = hash('sha1', http_build_query($paramsToSign) . $apiSecret);

        $response = Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy", [
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'public_id' => $publicId,
            'signature' => $signature,
        ]);

        return $response->successful() && ($response->json('result') ?? '') === 'ok';
    }
}
