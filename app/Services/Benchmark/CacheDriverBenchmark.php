<?php

namespace App\Services\Benchmark;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CacheDriverBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @param  null|callable(array<string, mixed> $progress): void  $onProgress
     * @return array{results: array<string, mixed>, winner: array<string, string>}
     */
    public function run(int $iterations = 100, ?callable $onProgress = null): array
    {
        $drivers = ['file', 'database', 'redis'];
        $operations = ['put', 'get_hit', 'get_miss', 'forget', 'remember', 'flush'];

        // Use a non-trivial payload so store footprint (KB) is visible in charts.
        $payloadValue = str_repeat('x', 10 * 1024); // ~10KB

        $results = [];
        $winners = [];

        $total = count($drivers) * count($operations) * max(1, $iterations);

        foreach ($drivers as $driverIndex => $driver) {
            try {
                $store = Cache::store($driver);
                $this->assertStoreHealthy($store, $driver);
                $baseKey = "bench:drivers:{$driver}:";

                $operationsCount = count($operations);

                $progress = function (int $operationIndex, string $op, int $opDone, int $opTotal) use ($total, $driver, $onProgress, $iterations, $driverIndex, $operationsCount) {
                    if ($onProgress === null) {
                        return;
                    }

                    $opTotal = max(1, $opTotal);
                    $base = (($driverIndex * $operationsCount) + $operationIndex) * max(1, $iterations);
                    $doneForOp = $base + $opDone;
                    $percent = $total > 0 ? (int) floor(($doneForOp / $total) * 100) : 0;

                    $onProgress([
                        'done' => $doneForOp,
                        'total' => $total,
                        'percent' => min(100, $percent),
                        'driver' => $driver,
                        'operation' => $op,
                        'message' => "{$driver} • {$op} ({$opDone}/{$opTotal})",
                    ]);
                };

                $results[$driver] = [];

                // put
                $store->flush();
                $keys = [$baseKey.'k'];
                $put = $this->runner->run(
                    operation: fn () => $store->put($baseKey.'k', $payloadValue, 3600),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(0, 'put', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['put'] = $this->attachStoreFootprint($store, $driver, $keys, $put);

                // get_hit
                $store->flush();
                $store->put($baseKey.'hit', $payloadValue, 3600);
                $keys = [$baseKey.'hit'];
                $getHit = $this->runner->run(
                    operation: fn () => $store->get($baseKey.'hit'),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(1, 'get_hit', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['get_hit'] = $this->attachStoreFootprint($store, $driver, $keys, $getHit);

                // get_miss
                $store->flush();
                $keys = [];
                $getMiss = $this->runner->run(
                    operation: fn () => $store->get($baseKey.'missing'),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(2, 'get_miss', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['get_miss'] = $this->attachStoreFootprint($store, $driver, $keys, $getMiss);

                // forget
                $store->flush();
                $keys = [$baseKey.'forget'];
                $forget = $this->runner->run(
                    operation: function () use ($store, $baseKey, $payloadValue) {
                        $key = $baseKey.'forget';
                        $store->put($key, $payloadValue, 3600);
                        $store->forget($key);
                    },
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(3, 'forget', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['forget'] = $this->attachStoreFootprint($store, $driver, $keys, $forget);

                // remember (unique keys so each iteration is a miss + write)
                $store->flush();
                $rememberKeys = [];
                $i = 0;
                $remember = $this->runner->run(
                    operation: function () use ($store, $baseKey, $payloadValue, &$rememberKeys, &$i) {
                        $key = $baseKey.'remember:'.$i;
                        $rememberKeys[] = $key;
                        $i++;
                        $store->remember($key, 3600, fn () => $payloadValue);
                    },
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(4, 'remember', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['remember'] = $this->attachStoreFootprint($store, $driver, $rememberKeys, $remember);

                // flush
                $store->flush();
                $keys = [];
                $flush = $this->runner->run(
                    operation: fn () => $store->flush(),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(5, 'flush', $opDone, $opTotal),
                )->toArray();
                $results[$driver]['flush'] = $this->attachStoreFootprint($store, $driver, $keys, $flush);
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

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function attachStoreFootprint(Repository $store, string $driver, array $keys, array $result): array
    {
        $bytes = $this->storeFootprintBytes($store, $driver, $keys);

        return $result + [
            'store_bytes' => $bytes,
            'store_kb' => $bytes !== null ? round($bytes / 1024, 4) : null,
        ];
    }

    /**
     * @param  list<string>  $keys
     */
    private function storeFootprintBytes(Repository $store, string $driver, array $keys): ?int
    {
        if (count($keys) === 0) {
            return 0;
        }

        $storeImpl = $store->getStore();
        $prefix = method_exists($storeImpl, 'getPrefix') ? (string) $storeImpl->getPrefix() : '';
        $prefixedKeys = array_map(static fn (string $k) => $prefix.$k, $keys);

        if ($driver === 'file') {
            if (! method_exists($storeImpl, 'path')) {
                return null;
            }

            $bytes = 0;
            foreach ($prefixedKeys as $k) {
                $path = $storeImpl->path($k);
                if (is_string($path) && is_file($path)) {
                    $bytes += (int) filesize($path);
                }
            }

            return $bytes;
        }

        if ($driver === 'database') {
            if (! Schema::hasTable('cache')) {
                return null;
            }

            $sum = DB::table('cache')
                ->whereIn('key', $prefixedKeys)
                ->selectRaw('SUM(LENGTH(value)) as bytes')
                ->value('bytes');

            return (int) ($sum ?? 0);
        }

        if ($driver === 'redis') {
            try {
                $connection = (string) config('cache.stores.redis.connection', 'cache');
                $redis = Redis::connection($connection);
            } catch (Throwable) {
                return null;
            }

            $bytes = 0;
            $connectionPrefix = '';
            foreach ($prefixedKeys as $k) {
                try {
                    $client = $redis->client();
                    if (is_object($client) && method_exists($client, 'executeRaw')) {
                        if ($connectionPrefix === '' && method_exists($client, 'getOptions')) {
                            $options = $client->getOptions();
                            $connectionPrefix = (string) ($options->prefix ?? '');
                        }

                        $rawKey = $connectionPrefix !== '' ? $connectionPrefix.$k : $k;

                        $usage = $client->executeRaw(['MEMORY', 'USAGE', $rawKey]);
                        if (is_numeric($usage)) {
                            $bytes += (int) $usage;
                        }
                    }
                } catch (Throwable) {
                    // ignore per-key measurement failures
                }
            }

            return $bytes;
        }

        return null;
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
