<?php

namespace App\Jobs\IndexMediaToCBIR;

use App\Observers\MediaObserver\MediaObserver;
use App\Services\CBIRService\CBIRService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class IndexMediaToCBIR implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected int $mediaId;

    public function __construct(int $mediaId)
    {
        $this->mediaId = $mediaId;
    }

    public function handle(CBIRService $cbirService): void
    {
        $media = Media::find($this->mediaId);
        if (! $media) {
            Log::warning("[IndexMediaToCBIR] Media #{$this->mediaId} not found, skipping index.");
            return;
        }

        if (! in_array($media->collection_name, MediaObserver::indexableCollections(), true)) {
            return;
        }

        Log::info("[IndexMediaToCBIR] Indexing media #{$this->mediaId} (collection: {$media->collection_name})");

        $cbirService->indexMedia($media);
    }
}
