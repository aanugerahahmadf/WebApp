<?php

namespace App\Observers\MediaObserver;

use App\Jobs\IndexMediaToCBIR\IndexMediaToCBIR;
use App\Services\CBIRService\CBIRService;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    protected $cbirService;

    public function __construct(CBIRService $cbirService)
    {
        $this->cbirService = $cbirService;
    }

    /**
     * Media collections that must end up in the CBIR index.
     *
     * `catalog_image` is the only photo collection: every catalog photo lives
     * there (the former package photos were moved onto their catalog).
     *
     * @return array<int, string>
     */
    public static function indexableCollections(): array
    {
        return ['catalog_image', 'gallery', 'category_image'];
    }

    public function created(Media $media)
    {
        if (in_array($media->collection_name, static::indexableCollections(), true)) {
            // Gunakan queue job agar media dari mobile (Capacitor) tetap terindeks
            // di server, meskipun request berasal dari device yang tidak bisa
            // menjangkau AI Core secara langsung.
            try {
                IndexMediaToCBIR::dispatch($media->id);
            } catch (\Throwable $e) {
                Log::warning('[MediaObserver] Failed to dispatch CBIR index job: '.$e->getMessage());
            }
        }
    }

    public function deleted(Media $media)
    {
        try {
            $this->cbirService->removeFromIndex($media->id);
        } catch (\Throwable $e) {
            Log::warning('[MediaObserver] CBIR remove failed: '.$e->getMessage());
        }
    }
}
