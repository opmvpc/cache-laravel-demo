<?php

namespace App\Http\Controllers;

use App\Services\Benchmark\CacheDriverBenchmark;
use App\Services\Benchmark\DataSizeBenchmark;
use App\Services\Benchmark\FibonacciBenchmark;
use App\Services\Benchmark\SqlQueryBenchmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        $results = $benchmark->run($iterations, $datasetSize, null, ['file', 'database', 'redis']);

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

    public function streamDrivers(Request $request, CacheDriverBenchmark $benchmark)
    {
        $iterations = (int) ($request->query('iterations', 100));
        $iterations = max(1, min(5000, $iterations));

        return $this->stream(function (callable $emit) use ($benchmark, $iterations) {
            $emit('start', ['benchmark' => 'cache_drivers', 'iterations' => $iterations]);

            $result = $benchmark->run($iterations, fn (array $p) => $emit('progress', $p));

            $payload = [
                'benchmark' => 'cache_drivers',
                'timestamp' => now()->toIso8601String(),
                'config' => ['iterations' => $iterations],
            ] + $result;

            Cache::store('file')->put('benchmark:last:cache_drivers', $payload, now()->addHours(6));
            $emit('result', $payload);
        });
    }

    public function streamSql(Request $request, SqlQueryBenchmark $benchmark)
    {
        $iterations = (int) ($request->query('iterations', 25));
        $iterations = max(1, min(1000, $iterations));
        $datasetSize = (int) ($request->query('dataset_size', 1000));
        $datasetSize = in_array($datasetSize, [100, 1000, 10000, 50000], true) ? $datasetSize : 1000;

        return $this->stream(function (callable $emit) use ($benchmark, $iterations, $datasetSize) {
            $emit('start', ['benchmark' => 'sql_queries', 'iterations' => $iterations, 'dataset_size' => $datasetSize]);

            $results = $benchmark->run($iterations, $datasetSize, fn (array $p) => $emit('progress', $p), ['file', 'database', 'redis']);

            $payload = [
                'benchmark' => 'sql_queries',
                'timestamp' => now()->toIso8601String(),
                'config' => ['iterations' => $iterations, 'dataset_size' => $datasetSize],
                'results' => $results,
            ];

            Cache::store('file')->put('benchmark:last:sql_queries', $payload, now()->addHours(6));
            $emit('result', $payload);
        });
    }

    public function streamFibonacci(FibonacciBenchmark $benchmark)
    {
        return $this->stream(function (callable $emit) use ($benchmark) {
            $emit('start', ['benchmark' => 'fibonacci']);

            $results = $benchmark->run(fn (array $p) => $emit('progress', $p));

            $payload = [
                'benchmark' => 'fibonacci',
                'timestamp' => now()->toIso8601String(),
                'config' => [],
                'results' => $results,
            ];

            Cache::store('file')->put('benchmark:last:fibonacci', $payload, now()->addHours(6));
            $emit('result', $payload);
        });
    }

    public function streamDataSize(Request $request, DataSizeBenchmark $benchmark)
    {
        $iterations = (int) ($request->query('iterations', 100));
        $iterations = max(1, min(5000, $iterations));
        $driver = (string) ($request->query('driver', 'file'));
        $driver = in_array($driver, ['file', 'database', 'redis'], true) ? $driver : 'file';

        return $this->stream(function (callable $emit) use ($benchmark, $iterations, $driver) {
            $emit('start', ['benchmark' => 'data_size', 'iterations' => $iterations, 'driver' => $driver]);

            $results = $benchmark->run($driver, $iterations, fn (array $p) => $emit('progress', $p));

            $payload = [
                'benchmark' => 'data_size',
                'timestamp' => now()->toIso8601String(),
                'config' => ['iterations' => $iterations, 'driver' => $driver],
                'results' => $results,
            ];

            Cache::store('file')->put('benchmark:last:data_size', $payload, now()->addHours(6));
            $emit('result', $payload);
        });
    }

    private function stream(callable $callback)
    {
        return response()->stream(function () use ($callback) {
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            set_time_limit(0);

            $emit = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data)."\n\n";
                @ob_flush();
                @flush();
            };

            try {
                $callback($emit);
            } catch (\Throwable $e) {
                Log::error('Benchmark stream failed', ['exception' => $e]);
                $emit('server_error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
