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
     * @return array{driver: string, results: array<string, array<string, mixed>>}
     */
    public function run(string $driver = 'file', int $iterations = 100): array
    {
        $store = Cache::store($driver);
        $store->flush();

        $sizes = [
            '1kb' => 1024,
            '10kb' => 10 * 1024,
            '100kb' => 100 * 1024,
            '1mb' => 1024 * 1024,
        ];

        $results = [];

        foreach ($sizes as $label => $bytes) {
            $payload = str_repeat('a', $bytes);
            $key = "bench:datasize:{$driver}:{$label}";

            $results[$label] = [
                'bytes' => $bytes,
                'put' => $this->runner->run(
                    operation: fn () => $store->put($key, $payload, 3600),
                    iterations: $iterations
                )->toArray(),
                'get' => $this->runner->run(
                    operation: fn () => $store->get($key),
                    iterations: $iterations
                )->toArray(),
            ];
        }

        return [
            'driver' => $driver,
            'results' => $results,
        ];
    }
}

