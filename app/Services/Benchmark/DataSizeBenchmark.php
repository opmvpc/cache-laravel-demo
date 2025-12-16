<?php

namespace App\Services\Benchmark;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class DataSizeBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @param  null|callable(array<string, mixed> $progress): void  $onProgress
     * @return array{driver: string, results: array<string, array<string, mixed>>}
     */
    public function run(string $driver = 'file', int $iterations = 100, ?callable $onProgress = null): array
    {
        $store = Cache::store($driver);
        $store->flush();

        $sizes = [
            '1kb' => 1024,
            '10kb' => 10 * 1024,
            '100kb' => 100 * 1024,
            '1mb' => 1024 * 1024,
        ];

        $ops = ['put', 'get'];
        $total = count($sizes) * count($ops) * max(1, $iterations);

        $results = [];

        $sizeIndex = 0;
        foreach ($sizes as $label => $bytes) {
            $payload = str_repeat('a', $bytes);
            $key = "bench:datasize:{$driver}:{$label}";

            $progress = function (int $operationIndex, string $op, int $opDone, int $opTotal) use ($onProgress, $label, $driver, $iterations, $total, $sizeIndex) {
                if ($onProgress === null) {
                    return;
                }

                $base = (($sizeIndex * 2) + $operationIndex) * max(1, $iterations);
                $done = $base + $opDone;
                $percent = $total > 0 ? (int) floor(($done / $total) * 100) : 0;

                $onProgress([
                    'done' => $done,
                    'total' => $total,
                    'percent' => min(100, $percent),
                    'driver' => $driver,
                    'size' => $label,
                    'operation' => $op,
                    'message' => "{$driver} • {$label} • {$op} ({$opDone}/{$opTotal})",
                ]);
            };

            $results[$label] = [
                'bytes' => $bytes,
                'put' => $this->runner->run(
                    operation: fn () => $store->put($key, $payload, 3600),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(0, 'put', $opDone, $opTotal),
                )->toArray(),
                'get' => $this->runner->run(
                    operation: fn () => $store->get($key),
                    iterations: $iterations,
                    onProgress: fn (int $opDone, int $opTotal) => $progress(1, 'get', $opDone, $opTotal),
                )->toArray(),
            ];

            $sizeIndex++;
        }

        return [
            'driver' => $driver,
            'results' => $results,
        ];
    }
}
