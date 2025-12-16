<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @var list<int>
     */
    public static array $authorIds = [];

    /**
     * @var list<int>
     */
    public static array $categoryIds = [];

    public function definition(): array
    {
        $title = Str::title(fake()->words(fake()->numberBetween(3, 8), true));
        $slug = Str::slug($title).'-'.Str::lower(Str::random(6));

        $published = fake()->boolean(70);
        $publishedAt = $published ? fake()->dateTimeBetween('-2 years', 'now') : null;

        return [
            'title' => $title,
            'slug' => $slug,
            'content' => fake()->paragraphs(fake()->numberBetween(10, 30), true),
            'excerpt' => fake()->sentence(),
            'views' => $this->viewsDistribution(),
            'published' => $published,
            'published_at' => $publishedAt,
            'author_id' => count(static::$authorIds) > 0 ? fake()->randomElement(static::$authorIds) : Author::factory(),
            'category_id' => count(static::$categoryIds) > 0 ? fake()->randomElement(static::$categoryIds) : Category::factory(),
        ];
    }

    private function viewsDistribution(): int
    {
        $roll = fake()->randomFloat(4, 0, 1);

        if ($roll < 0.80) {
            return fake()->numberBetween(0, 100);
        }

        if ($roll < 0.95) {
            return fake()->numberBetween(100, 1000);
        }

        if ($roll < 0.99) {
            return fake()->numberBetween(1000, 10_000);
        }

        return fake()->numberBetween(10_000, 100_000);
    }
}

