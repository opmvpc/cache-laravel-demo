<?php

namespace App\Services\Benchmark;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SqlQueryBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @param  null|callable(array<string, mixed> $progress): void  $onProgress
     * @param  list<string>  $stores
     * @return array{dataset_size: int, effective_dataset_size: int, db_article_count: int, cache_stores: list<string>, variants: array<string, mixed>}
     */
    public function run(int $iterations = 25, int $datasetSize = 1000, ?callable $onProgress = null, array $stores = ['file', 'database', 'redis']): array
    {
        $iterations = max(1, $iterations);
        $datasetSize = max(1, $datasetSize);

        if (! Schema::hasTable('articles')) {
            throw new \RuntimeException('Missing tables. Run migrations and seed data first.');
        }

        $dbArticleCount = (int) DB::table('articles')->count();
        $effectiveDatasetSize = max(1, min($datasetSize, max(1, $dbArticleCount)));
        $ids = fn () => $this->articleIdsSubquery($effectiveDatasetSize);

        $variants = [
            'simple' => fn () => DB::table('articles')->whereIn('id', $ids())->limit(100)->get(),
            'relations' => fn () => Article::query()->whereIn('id', $ids())->with(['author', 'tags', 'comments'])->limit(100)->get(),
            'aggregations' => fn () => DB::table('articles')->whereIn('id', $ids())
                ->select('category_id', DB::raw('COUNT(*) as count'), DB::raw('AVG(views) as avg_views'))
                ->groupBy('category_id')
                ->get(),
            'complex' => fn () => $this->complexQuery($effectiveDatasetSize, $ids()),
        ];

        $stores = array_values(array_unique(array_values($stores)));
        $stores = array_values(array_filter($stores, static fn (string $s) => in_array($s, ['file', 'database', 'redis'], true)));
        if (count($stores) === 0) {
            $stores = ['file', 'database', 'redis'];
        }

        $storesCount = count($stores);
        $perVariantSteps = $iterations + ($storesCount * (1 + $iterations));
        $totalSteps = count($variants) * $perVariantSteps;

        $results = [];
        $variantIndex = 0;

        foreach ($variants as $name => $query) {
            $variantBase = $variantIndex * $perVariantSteps;

            $direct = $this->runner->run(
                operation: fn () => $query(),
                iterations: $iterations,
                onProgress: $this->progressAdapter($onProgress, $totalSteps, $variantBase, $name, 'direct', 'no-cache', $iterations),
            )->toArray();

            $results[$name] = [
                'direct' => $direct,
                'stores' => [],
            ];

            foreach ($stores as $storeIndex => $storeName) {
                try {
                    $store = Cache::store($storeName);
                    $cacheKey = "bench:sql:{$name}:{$datasetSize}:{$storeName}";
                    $this->flushStore($storeName, $store);

                    $storeBase = $variantBase + $iterations + ($storeIndex * (1 + $iterations));

                    $cachedMiss = $this->runner->run(
                        operation: fn () => $store->remember($cacheKey, 60, fn () => $query()),
                        iterations: 1,
                        warmup: 0,
                        onProgress: $this->progressAdapter($onProgress, $totalSteps, $storeBase, $name, 'cached_miss', $storeName, 1),
                    )->toArray();

                    $cachedHit = $this->runner->run(
                        operation: fn () => $store->remember($cacheKey, 60, fn () => $query()),
                        iterations: $iterations,
                        onProgress: $this->progressAdapter($onProgress, $totalSteps, $storeBase + 1, $name, 'cached_hit', $storeName, $iterations),
                    )->toArray();

                    $results[$name]['stores'][$storeName] = [
                        'cached_miss' => $cachedMiss,
                        'cached_hit' => $cachedHit,
                    ];

                    $this->flushStore($storeName, $store);
                } catch (\Throwable $e) {
                    $results[$name]['stores'][$storeName] = [
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $variantIndex++;
        }

        return [
            'dataset_size' => $datasetSize,
            'effective_dataset_size' => $effectiveDatasetSize,
            'db_article_count' => $dbArticleCount,
            'variants' => $results,
            'cache_stores' => $stores,
        ];
    }

    private function complexQuery(int $datasetSize, $idsSubquery)
    {
        return DB::table('articles')
            ->whereIn('articles.id', $idsSubquery)
            ->join('authors', 'authors.id', '=', 'articles.author_id')
            ->join('categories', 'categories.id', '=', 'articles.category_id')
            ->leftJoin('comments', 'comments.article_id', '=', 'articles.id')
            ->leftJoin('article_tag', 'article_tag.article_id', '=', 'articles.id')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('COUNT(DISTINCT articles.id) as articles_count'),
                DB::raw('AVG(articles.views) as avg_views'),
                DB::raw('COUNT(DISTINCT comments.id) as comments_count'),
                DB::raw('COUNT(DISTINCT article_tag.tag_id) as tags_count'),
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('articles_count')
            ->limit(min(50, $datasetSize))
            ->get();
    }

    private function articleIdsSubquery(int $datasetSize)
    {
        return DB::table('articles')
            ->select('id')
            ->orderBy('id')
            ->limit($datasetSize);
    }

    /**
     * @return null|callable(int $done, int $total): void
     */
    private function progressAdapter(?callable $onProgress, int $globalTotal, int $baseDone, string $variant, string $mode, string $store, int $opTotal)
    {
        if ($onProgress === null) {
            return null;
        }

        return function (int $opDone, int $ignoredTotal) use ($onProgress, $globalTotal, $baseDone, $variant, $mode, $store, $opTotal) {
            $done = min($globalTotal, $baseDone + $opDone);
            $percent = $globalTotal > 0 ? (int) floor(($done / $globalTotal) * 100) : 0;
            $onProgress([
                'done' => $done,
                'total' => $globalTotal,
                'percent' => min(100, $percent),
                'variant' => $variant,
                'mode' => $mode,
                'store' => $store,
                'message' => "{$variant} • {$store} • {$mode} ({$opDone}/{$opTotal})",
            ]);
        };
    }

    private function flushStore(string $storeName, CacheRepository $store): void
    {
        try {
            $store->flush();
        } catch (\Throwable $e) {
            if ($storeName === 'redis') {
                // if Redis is down, surface the original error
                throw $e;
            }
        }
    }
}
