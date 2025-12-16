<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Database\Factories\ArticleFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BenchmarkSeedCommand extends Command
{
    protected $signature = 'benchmark:seed {count=1000 : Number of articles to create} {--fresh : Truncate tables before seeding} {--chunk=500 : Insert in chunks to reduce memory}';

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
        $statusKey = 'benchmark:seed:status';

        $statusStore->put($statusKey, [
            'running' => true,
            'started_at' => now()->toIso8601String(),
            'article_target' => $articleCount,
            'article_created' => 0,
            'message' => 'Starting...',
        ], now()->addHours(6));

        if ($fresh) {
            $this->warn('Truncating tables...');
            Schema::disableForeignKeyConstraints();
            DB::table('comments')->truncate();
            DB::table('article_tag')->truncate();
            DB::table('articles')->truncate();
            DB::table('tags')->truncate();
            DB::table('categories')->truncate();
            DB::table('authors')->truncate();
            Schema::enableForeignKeyConstraints();
        }

        $authorCount = max(10, intdiv($articleCount, 50));
        $categoryCount = fake()->numberBetween(10, 20);
        $tagCount = fake()->numberBetween(30, 50);

        $this->info("Creating {$authorCount} authors, {$categoryCount} categories, {$tagCount} tags...");

        $authors = Author::factory()->count($authorCount)->create();
        $categories = Category::factory()->count($categoryCount)->create();
        $tags = Tag::factory()->count($tagCount)->create();

        ArticleFactory::$authorIds = $authors->modelKeys();
        ArticleFactory::$categoryIds = $categories->modelKeys();

        $tagIds = $tags->modelKeys();

        $created = 0;
        $this->info("Creating {$articleCount} articles in chunks of {$chunkSize}...");

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

                $commentsForArticle = max(0, fake()->numberBetween(0, 6));
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

            $statusStore->put($statusKey, [
                'running' => true,
                'started_at' => $statusStore->get($statusKey)['started_at'] ?? now()->toIso8601String(),
                'article_target' => $articleCount,
                'article_created' => $created,
                'message' => "Created {$created}/{$articleCount} articles...",
            ], now()->addHours(6));

            $this->line("Created {$created}/{$articleCount} articles...");
        }

        $summary = [
            'running' => false,
            'finished_at' => now()->toIso8601String(),
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
}

