<?php

namespace App\Http\Controllers;

use App\Services\Benchmark\CacheDriverBenchmark;
use App\Services\Benchmark\DataSizeBenchmark;
use App\Services\Benchmark\FibonacciBenchmark;
use App\Services\Benchmark\SqlQueryBenchmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class BenchmarkController extends Controller
{
    public function index()
    {
        return view('benchmark.index', [
            'defaultIterations' => 100,
            'defaultDatasetSize' => 1000,
        ]);
    }

    public function runDrivers(Request $request, CacheDriverBenchmark $benchmark)
    {
        $data = $request->validate([
            'iterations' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $iterations = (int) ($data['iterations'] ?? 100);

        $payload = [
            'benchmark' => 'cache_drivers',
            'timestamp' => now()->toIso8601String(),
            'config' => [
                'iterations' => $iterations,
            ],
        ] + $benchmark->run($iterations);

        Cache::store('file')->put('benchmark:last:cache_drivers', $payload, now()->addHours(6));

        return response()->json($payload);
    }

    public function runSql(Request $request, SqlQueryBenchmark $benchmark)
    {
        $data = $request->validate([
            'iterations' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'dataset_size' => ['nullable', 'integer', Rule::in([100, 1000, 10000, 50000])],
        ]);

        $iterations = (int) ($data['iterations'] ?? 25);
        $datasetSize = (int) ($data['dataset_size'] ?? 1000);

        $results = $benchmark->run($iterations, $datasetSize);

        $payload = [
            'benchmark' => 'sql_queries',
            'timestamp' => now()->toIso8601String(),
            'config' => [
                'iterations' => $iterations,
                'dataset_size' => $datasetSize,
            ],
            'results' => $results,
        ];

        Cache::store('file')->put('benchmark:last:sql_queries', $payload, now()->addHours(6));

        return response()->json($payload);
    }

    public function runFibonacci(FibonacciBenchmark $benchmark)
    {
        $payload = [
            'benchmark' => 'fibonacci',
            'timestamp' => now()->toIso8601String(),
            'config' => [],
            'results' => $benchmark->run(),
        ];

        Cache::store('file')->put('benchmark:last:fibonacci', $payload, now()->addHours(6));

        return response()->json($payload);
    }

    public function runDataSize(Request $request, DataSizeBenchmark $benchmark)
    {
        $data = $request->validate([
            'iterations' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'driver' => ['nullable', 'string', Rule::in(['file', 'database', 'redis'])],
        ]);

        $iterations = (int) ($data['iterations'] ?? 100);
        $driver = (string) ($data['driver'] ?? 'file');

        $payload = [
            'benchmark' => 'data_size',
            'timestamp' => now()->toIso8601String(),
            'config' => [
                'iterations' => $iterations,
                'driver' => $driver,
            ],
            'results' => $benchmark->run($driver, $iterations),
        ];

        Cache::store('file')->put('benchmark:last:data_size', $payload, now()->addHours(6));

        return response()->json($payload);
    }

    public function runAll(Request $request, CacheDriverBenchmark $drivers, SqlQueryBenchmark $sql, FibonacciBenchmark $fib, DataSizeBenchmark $size)
    {
        $data = $request->validate([
            'iterations' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'dataset_size' => ['nullable', 'integer', Rule::in([100, 1000, 10000, 50000])],
        ]);

        $iterations = (int) ($data['iterations'] ?? 100);
        $datasetSize = (int) ($data['dataset_size'] ?? 1000);

        $payload = [
            'benchmark' => 'all',
            'timestamp' => now()->toIso8601String(),
            'config' => [
                'iterations' => $iterations,
                'dataset_size' => $datasetSize,
            ],
            'results' => [
                'cache_drivers' => $drivers->run($iterations),
                'sql_queries' => $sql->run(min(50, max(1, (int) round($iterations / 4))), $datasetSize),
                'fibonacci' => $fib->run(),
                'data_size' => $size->run('file', $iterations),
            ],
        ];

        Cache::store('file')->put('benchmark:last:all', $payload, now()->addHours(6));

        return response()->json($payload);
    }
}

