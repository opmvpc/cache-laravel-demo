<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Database\Factories\ArticleFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BenchmarkSeedCommand extends Command
{
    protected $signature = 'benchmark:seed
        {count=1000 : Number of articles to create}
        {--fresh : Truncate tables before seeding}
        {--chunk=500 : Insert in chunks to reduce memory}
        {--authors= : Override authors count}
        {--categories= : Override categories count}
        {--tags= : Override tags count}
        {--comments-per-article=3 : Average comments per article}
        {--job= : Job id for UI progress tracking}';

    protected $description = 'Seed the database with realistic data for cache benchmarks.';

    public function handle(): int
    {
        $articleCount = max(0, (int) $this->argument('count'));
        $fresh = (bool) $this->option('fresh');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if ($articleCount < 1) {
            $this->error('count must be >= 1');
            return self::FAILURE;
        }

        $statusStore = Cache::store('file');
        $jobId = (string) ($this->option('job') ?: Str::uuid()->toString());
        $statusKey = 'benchmark:seed:status:'.$jobId;

        $statusStore->put('benchmark:seed:last_job', $jobId, now()->addHours(6));

        $statusStore->put($statusKey, [
            'running' => true,
            'started_at' => now()->toIso8601String(),
            'job' => $jobId,
            'article_target' => $articleCount,
            'article_created' => 0,
            'phase' => 'starting',
            'message' => 'Starting...',
        ], now()->addHours(6));

        if (! Schema::hasTable('authors') || ! Schema::hasTable('articles')) {
            $statusStore->put($statusKey, [
                'running' => false,
                'job' => $jobId,
                'error' => 'Missing tables. Run migrations first.',
                'message' => 'Missing tables. Run migrations first.',
            ], now()->addHours(6));

            $this->error('Missing tables. Run migrations first.');
            return self::FAILURE;
        }

        if ($fresh) {
            $this->warn('Truncating tables...');
            $this->updateStatus($statusStore, $statusKey, $jobId, phase: 'truncate', message: 'Truncating tables...');

            Schema::disableForeignKeyConstraints();
            if (Schema::hasTable('comments')) {
                DB::table('comments')->truncate();
            }
            if (Schema::hasTable('article_tag')) {
                DB::table('article_tag')->truncate();
            }
            if (Schema::hasTable('articles')) {
                DB::table('articles')->truncate();
            }
            if (Schema::hasTable('tags')) {
                DB::table('tags')->truncate();
            }
            if (Schema::hasTable('categories')) {
                DB::table('categories')->truncate();
            }
            if (Schema::hasTable('authors')) {
                DB::table('authors')->truncate();
            }
            Schema::enableForeignKeyConstraints();
        }

        $authorOverride = $this->option('authors');
        $categoryOverride = $this->option('categories');
        $tagOverride = $this->option('tags');
        $commentsPerArticle = max(0.0, (float) $this->option('comments-per-article'));

        $authorCount = $authorOverride !== null ? max(1, (int) $authorOverride) : max(10, intdiv($articleCount, 50));
        $categoryCount = $categoryOverride !== null ? max(1, (int) $categoryOverride) : fake()->numberBetween(10, 20);
        $tagCount = $tagOverride !== null ? max(1, (int) $tagOverride) : fake()->numberBetween(30, 50);

        $this->info("Creating {$authorCount} authors, {$categoryCount} categories, {$tagCount} tags...");
        $this->updateStatus($statusStore, $statusKey, $jobId, phase: 'taxonomy', message: "Creating {$authorCount} authors, {$categoryCount} categories, {$tagCount} tags...");

        $authors = Author::factory()->count($authorCount)->create();
        $categories = Category::factory()->count($categoryCount)->create();
        $tags = Tag::factory()->count($tagCount)->create();

        ArticleFactory::$authorIds = $authors->modelKeys();
        ArticleFactory::$categoryIds = $categories->modelKeys();

        $tagIds = $tags->modelKeys();

        $created = 0;
        $this->info("Creating {$articleCount} articles in chunks of {$chunkSize}...");
        $this->updateStatus($statusStore, $statusKey, $jobId, phase: 'articles', message: "Creating {$articleCount} articles...");

        while ($created < $articleCount) {
            $take = min($chunkSize, $articleCount - $created);

            $articles = Article::factory()->count($take)->create();

            $pivotRows = [];
            $commentRows = [];

            foreach ($articles as $article) {
                $attachCount = fake()->numberBetween(0, 5);
                if ($attachCount > 0 && count($tagIds) > 0) {
                    $selected = Arr::wrap(Arr::random($tagIds, min($attachCount, count($tagIds))));
                    foreach ($selected as $tagId) {
                        $pivotRows[] = [
                            'article_id' => $article->id,
                            'tag_id' => $tagId,
                        ];
                    }
                }

                $commentsForArticle = $this->commentsForArticle($commentsPerArticle);
                for ($i = 0; $i < $commentsForArticle; $i++) {
                    $commentRows[] = [
                        'article_id' => $article->id,
                        'author_name' => fake()->name(),
                        'content' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (count($pivotRows) > 0) {
                DB::table('article_tag')->insertOrIgnore($pivotRows);
            }

            if (count($commentRows) > 0) {
                DB::table('comments')->insert($commentRows);
            }

            $created += $take;

            $this->updateStatus($statusStore, $statusKey, $jobId, phase: 'articles', articleCreated: $created, articleTarget: $articleCount, message: "Created {$created}/{$articleCount} articles...");

            $this->line("Created {$created}/{$articleCount} articles...");
        }

        $summary = [
            'running' => false,
            'finished_at' => now()->toIso8601String(),
            'job' => $jobId,
            'article_target' => $articleCount,
            'article_created' => $created,
            'counts' => [
                'authors' => Author::count(),
                'categories' => Category::count(),
                'tags' => Tag::count(),
                'articles' => Article::count(),
                'comments' => DB::table('comments')->count(),
            ],
            'message' => 'Done',
        ];

        $statusStore->put($statusKey, $summary, now()->addHours(6));
        $this->info('Seeding completed.');

        return self::SUCCESS;
    }

    private function commentsForArticle(float $avg): int
    {
        if ($avg <= 0) {
            return 0;
        }

        $low = max(0, (int) floor($avg - 2));
        $high = max($low, (int) ceil($avg + 2));

        return (int) fake()->numberBetween($low, $high);
    }

    private function updateStatus($store, string $key, string $jobId, string $phase, string $message, ?int $articleCreated = null, ?int $articleTarget = null): void
    {
        $current = $store->get($key) ?: [];

        $store->put($key, array_filter([
            'running' => true,
            'job' => $jobId,
            'started_at' => $current['started_at'] ?? now()->toIso8601String(),
            'article_target' => $articleTarget ?? ($current['article_target'] ?? null),
            'article_created' => $articleCreated ?? ($current['article_created'] ?? 0),
            'phase' => $phase,
            'message' => $message,
        ], static fn ($v) => $v !== null), now()->addHours(6));
    }
}
