<?php

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sql benchmark runs with seeded data', function () {
    $author = Author::factory()->create();
    $category = Category::factory()->create();
    $tags = Tag::factory()->count(3)->create();

    $articles = Article::factory()
        ->count(20)
        ->create([
            'author_id' => $author->id,
            'category_id' => $category->id,
        ]);

    foreach ($articles as $article) {
        $article->tags()->sync($tags->modelKeys());
        Comment::factory()->count(2)->create(['article_id' => $article->id]);
    }

    $this->postJson('/benchmark/sql', ['iterations' => 2, 'dataset_size' => 100])
        ->assertOk()
        ->assertJsonPath('benchmark', 'sql_queries')
        ->assertJsonStructure([
            'results' => [
                'variants' => [
                    'simple' => ['direct', 'cached_miss', 'cached_hit'],
                    'relations' => ['direct', 'cached_miss', 'cached_hit'],
                    'aggregations' => ['direct', 'cached_miss', 'cached_hit'],
                    'complex' => ['direct', 'cached_miss', 'cached_hit'],
                ],
            ],
        ]);
});

