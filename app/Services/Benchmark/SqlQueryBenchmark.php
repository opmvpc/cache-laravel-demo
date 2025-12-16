<?php

namespace App\Services\Benchmark;

use App\Models\Article;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SqlQueryBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @return array{dataset_size: int, variants: array<string, mixed>}
     */
    public function run(int $iterations = 25, int $datasetSize = 1000): array
    {
        $iterations = max(1, $iterations);
        $datasetSize = max(1, $datasetSize);

        if (! Schema::hasTable('articles')) {
            throw new \RuntimeException('Missing tables. Run migrations and seed data first.');
        }

        $variants = [
            'simple' => fn () => DB::table('articles')->limit(100)->get(),
            'relations' => fn () => Article::query()->with(['author', 'tags', 'comments'])->limit(100)->get(),
            'aggregations' => fn () => DB::table('articles')
                ->select('category_id', DB::raw('COUNT(*) as count'), DB::raw('AVG(views) as avg_views'))
                ->groupBy('category_id')
                ->get(),
            'complex' => fn () => $this->complexQuery($datasetSize),
        ];

        $results = [];
        foreach ($variants as $name => $query) {
            $cacheKey = "bench:sql:{$name}:{$datasetSize}";

            Cache::forget($cacheKey);

            $direct = $this->runner->run(
                operation: fn () => $query(),
                iterations: $iterations
            )->toArray();

            Cache::forget($cacheKey);
            $cachedMiss = $this->runner->run(
                operation: fn () => Cache::remember($cacheKey, 60, fn () => $query()),
                iterations: 1,
                warmup: 0
            )->toArray();

            $cachedHit = $this->runner->run(
                operation: fn () => Cache::remember($cacheKey, 60, fn () => $query()),
                iterations: $iterations
            )->toArray();

            $results[$name] = [
                'direct' => $direct,
                'cached_miss' => $cachedMiss,
                'cached_hit' => $cachedHit,
            ];
        }

        return [
            'dataset_size' => $datasetSize,
            'variants' => $results,
        ];
    }

    private function complexQuery(int $datasetSize)
    {
        return DB::table('articles')
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
}
