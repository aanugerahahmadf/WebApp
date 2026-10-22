<?php

namespace App\Services\CbirResultService;

use App\Models\Catalog\Catalog;
use Illuminate\Support\Collection;

/**
 * Turns raw AI Core CBIR rows into hydrated, renderable catalog items.
 *
 * Previously this lived on the (now removed) PackageResource and duplicated
 * three more times across CbirSearchPage / CbirCameraButton. Packages are no
 * longer browsable, so the only result type the UI can link to is `catalog`;
 * every other type -- including `package`, which is now a tier rather than a
 * browsable item -- is dropped.
 */
class CbirResultService
{
    /**
     * Hydrate CBIR rows into catalog payloads.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array{type: string, similarity: float, data: array<string, mixed>}>
     */
    public static function buildCbirMixedResults(array $results): array
    {
        $rows = self::normalizeRows($results);

        $ids = collect($rows)->pluck('owner_id')->unique()->filter()->values()->all();

        /** @var Collection<int, Catalog> $catalogs */
        $catalogs = Catalog::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with(['category', 'media'])
            ->get()
            ->keyBy('id');

        // Nothing the index returned survived (stale ids, deleted rows, or a
        // package-only index): show the catalog instead of an empty grid.
        if ($catalogs->isEmpty()) {
            $catalogs = Catalog::query()
                ->where('is_active', true)
                ->with(['category', 'media'])
                ->orderByDesc('is_featured')
                ->limit(24)
                ->get()
                ->keyBy('id');

            $rows = $catalogs->map(fn (Catalog $catalog): array => [
                'owner_id' => $catalog->id,
                'score' => 0,
            ])->all();
        }

        return collect($rows)
            ->unique(fn (array $row): string => 'catalog-'.($row['owner_id'] ?? 0))
            ->map(function (array $row) use ($catalogs) {
                $catalog = $catalogs->get($row['owner_id'] ?? 0);

                if (! $catalog instanceof Catalog) {
                    return null;
                }

                return [
                    'type' => 'catalog',
                    'similarity' => round(((float) ($row['score'] ?? 0)) * 100, 1),
                    'score' => (float) ($row['score'] ?? 0),
                    'owner_id' => (int) $catalog->id,
                    'data' => array_merge($catalog->toArray(), [
                        'image_url' => $catalog->image_url,
                        'thumbnail_url' => $catalog->thumbnail_url,
                        'category' => $catalog->category?->toArray(),
                    ]),
                ];
            })
            ->filter()
            ->sortByDesc('similarity')
            ->values()
            ->all();
    }

    /**
     * Drop non-catalog rows and map legacy type names onto `catalog`.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array{owner_id: int, score: float}>
     */
    protected static function normalizeRows(array $results): array
    {
        $rows = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $type = strtolower((string) ($result['type'] ?? 'catalog'));

            // Anything that is not a catalog is dropped: packages are tiers now
            // and have no detail page to link to, and no other type is valid.
            if ($type !== 'catalog' && $type !== 'catalogs') {
                continue;
            }

            $ownerId = (int) ($result['owner_id'] ?? $result['id'] ?? 0);

            if ($ownerId <= 0) {
                continue;
            }

            $rows[] = [
                'owner_id' => $ownerId,
                'score' => (float) ($result['score'] ?? $result['similarity'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Clamp a 0..1 similarity ratio into a whole-number percentage.
     */
    public static function formatSimilarityPct(float $value): int
    {
        // Some callers already pass a percentage (e.g. 42.5); only scale when
        // the value is clearly a ratio.
        $pct = $value <= 1.0 ? $value * 100 : $value;

        return (int) min(100, max(0, round($pct)));
    }
}
