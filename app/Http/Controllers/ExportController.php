<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function json(string $benchmark)
    {
        $payload = Cache::store('file')->get('benchmark:last:'.$benchmark);
        if (! is_array($payload)) {
            return response()->json(['error' => 'No stored results for this benchmark yet.'], 404);
        }

        return response()->json($payload);
    }

    public function csv(string $benchmark): StreamedResponse
    {
        $payload = Cache::store('file')->get('benchmark:last:'.$benchmark);
        if (! is_array($payload)) {
            abort(404, 'No stored results for this benchmark yet.');
        }

        $rows = $this->toCsvRows($benchmark, $payload);

        $filename = 'benchmark-'.Str::slug($benchmark).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return list<array<int, string|int|float|null>>
     */
    private function toCsvRows(string $benchmark, array $payload): array
    {
        if ($benchmark === 'cache_drivers') {
            $rows = [['operation', 'driver', 'min_ms', 'max_ms', 'avg_ms', 'std_dev_ms', 'memory_kb', 'iterations']];

            foreach (($payload['results'] ?? []) as $driver => $ops) {
                if (! is_array($ops)) {
                    continue;
                }
                foreach ($ops as $op => $stats) {
                    if (! is_array($stats) || ! isset($stats['avg'])) {
                        continue;
                    }
                    $rows[] = [
                        $op,
                        $driver,
                        $stats['min'] ?? null,
                        $stats['max'] ?? null,
                        $stats['avg'] ?? null,
                        $stats['std_dev'] ?? null,
                        $stats['memory_kb'] ?? null,
                        $stats['iterations'] ?? null,
                    ];
                }
            }

            return $rows;
        }

        if ($benchmark === 'sql_queries') {
            $rows = [['variant', 'cache_store', 'mode', 'min_ms', 'max_ms', 'avg_ms', 'std_dev_ms', 'memory_kb', 'iterations', 'db_queries']];
            $variants = $payload['results']['variants'] ?? [];

            foreach ($variants as $variant => $data) {
                if (! is_array($data)) {
                    continue;
                }

                $direct = $data['direct'] ?? null;
                if (is_array($direct) && isset($direct['avg'])) {
                    $rows[] = [
                        $variant,
                        'no-cache',
                        'direct',
                        $direct['min'] ?? null,
                        $direct['max'] ?? null,
                        $direct['avg'] ?? null,
                        $direct['std_dev'] ?? null,
                        $direct['memory_kb'] ?? null,
                        $direct['iterations'] ?? null,
                        $direct['db_queries'] ?? null,
                    ];
                }

                $stores = $data['stores'] ?? [];
                if (! is_array($stores)) {
                    continue;
                }

                foreach ($stores as $store => $modes) {
                    if (! is_array($modes)) {
                        continue;
                    }
                    foreach (['cached_miss', 'cached_hit'] as $mode) {
                        $stats = $modes[$mode] ?? null;
                        if (! is_array($stats) || ! isset($stats['avg'])) {
                            continue;
                        }
                        $rows[] = [
                            $variant,
                            $store,
                            $mode,
                            $stats['min'] ?? null,
                            $stats['max'] ?? null,
                            $stats['avg'] ?? null,
                            $stats['std_dev'] ?? null,
                            $stats['memory_kb'] ?? null,
                            $stats['iterations'] ?? null,
                            $stats['db_queries'] ?? null,
                        ];
                    }
                }
            }

            return $rows;
        }

        if ($benchmark === 'fibonacci') {
            $rows = [['n', 'method', 'value', 'time_ms', 'calls']];
            foreach (($payload['results']['cases'] ?? []) as $case) {
                if (! is_array($case) || ! isset($case['n'])) {
                    continue;
                }

                foreach (['naive', 'memoized', 'iterative'] as $method) {
                    $stats = $case[$method] ?? null;
                    if (! is_array($stats)) {
                        continue;
                    }

                    $rows[] = [
                        $case['n'],
                        $method,
                        $case['value'] ?? null,
                        $stats['avg'] ?? null,
                        $stats['calls'] ?? null,
                    ];
                }
            }

            return $rows;
        }

        if ($benchmark === 'data_size') {
            $rows = [['size', 'bytes', 'operation', 'min_ms', 'max_ms', 'avg_ms', 'std_dev_ms', 'memory_kb', 'iterations']];
            foreach (($payload['results']['results'] ?? []) as $size => $data) {
                if (! is_array($data)) {
                    continue;
                }
                foreach (['put', 'get'] as $op) {
                    $stats = $data[$op] ?? null;
                    if (! is_array($stats) || ! isset($stats['avg'])) {
                        continue;
                    }
                    $rows[] = [
                        $size,
                        $data['bytes'] ?? null,
                        $op,
                        $stats['min'] ?? null,
                        $stats['max'] ?? null,
                        $stats['avg'] ?? null,
                        $stats['std_dev'] ?? null,
                        $stats['memory_kb'] ?? null,
                        $stats['iterations'] ?? null,
                    ];
                }
            }

            return $rows;
        }

        return [['error', 'Unsupported benchmark key']];
    }
}
