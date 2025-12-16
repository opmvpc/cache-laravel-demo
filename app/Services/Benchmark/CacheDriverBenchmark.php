<?php

namespace App\Services\Benchmark;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheDriverBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @return array{results: array<string, mixed>, winner: array<string, string>}
     */
    public function run(int $iterations = 100): array
    {
        $drivers = ['file', 'database', 'redis'];
        $operations = ['put', 'get_hit', 'get_miss', 'forget', 'remember', 'flush'];

        $results = [];
        $winners = [];

        foreach ($drivers as $driver) {
            try {
                $store = Cache::store($driver);
                $this->assertStoreHealthy($store, $driver);
                $store->flush();

                $baseKey = "bench:drivers:{$driver}:";

                $results[$driver] = [
                    'put' => $this->runner->run(
                        operation: fn () => $store->put($baseKey.'k', 'value', 3600),
                        iterations: $iterations
                    )->toArray(),
                    'get_hit' => $this->benchmarkGetHit($store, $baseKey, $iterations),
                    'get_miss' => $this->runner->run(
                        operation: fn () => $store->get($baseKey.'missing'),
                        iterations: $iterations
                    )->toArray(),
                    'forget' => $this->benchmarkForget($store, $baseKey, $iterations),
                    'remember' => $this->runner->run(
                        operation: fn () => $store->remember($baseKey.'remember:'.uniqid('', true), 3600, fn () => 'value'),
                        iterations: $iterations
                    )->toArray(),
                    'flush' => $this->runner->run(
                        operation: fn () => $store->flush(),
                        iterations: $iterations
                    )->toArray(),
                ];
            } catch (Throwable $e) {
                $results[$driver] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        foreach ($operations as $op) {
            $bestDriver = null;
            $bestAvg = null;

            foreach ($drivers as $driver) {
                if (! isset($results[$driver][$op]['avg'])) {
                    continue;
                }

                $avg = $results[$driver][$op]['avg'];
                if ($bestAvg === null || $avg < $bestAvg) {
                    $bestAvg = $avg;
                    $bestDriver = $driver;
                }
            }

            if ($bestDriver !== null) {
                $winners[$op] = $bestDriver;
            }
        }

        $winners['overall'] = $this->overallWinner($results, $drivers, $operations) ?? 'n/a';

        return [
            'results' => $results,
            'winner' => $winners,
        ];
    }

    private function benchmarkGetHit(Repository $store, string $baseKey, int $iterations): array
    {
        $store->put($baseKey.'hit', 'value', 3600);

        return $this->runner->run(
            operation: fn () => $store->get($baseKey.'hit'),
            iterations: $iterations
        )->toArray();
    }

    private function benchmarkForget(Repository $store, string $baseKey, int $iterations): array
    {
        return $this->runner->run(
            operation: function () use ($store, $baseKey) {
                $key = $baseKey.'forget';
                $store->put($key, 'value', 3600);
                $store->forget($key);
            },
            iterations: $iterations
        )->toArray();
    }

    private function assertStoreHealthy(Repository $store, string $driver): void
    {
        $key = 'bench:health:'.$driver.':'.uniqid('', true);
        $store->put($key, '1', 5);
        $store->get($key);
        $store->forget($key);
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  list<string>  $drivers
     * @param  list<string>  $operations
     */
    private function overallWinner(array $results, array $drivers, array $operations): ?string
    {
        $scores = [];

        foreach ($drivers as $driver) {
            $sum = 0.0;
            $count = 0;

            foreach ($operations as $op) {
                if (! isset($results[$driver][$op]['avg'])) {
                    continue;
                }
                $sum += (float) $results[$driver][$op]['avg'];
                $count++;
            }

            if ($count > 0) {
                $scores[$driver] = $sum / $count;
            }
        }

        asort($scores);

        return array_key_first($scores);
    }
}

