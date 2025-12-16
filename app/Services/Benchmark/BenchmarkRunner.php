<?php

namespace App\Services\Benchmark;

use Closure;

class BenchmarkRunner
{
    /**
     * @param  callable(): void  $operation
     * @param  null|callable(int $done, int $total): void  $onProgress
     */
    public function run(callable $operation, int $iterations = 100, int $warmup = 2, ?callable $onProgress = null, int $progressEvery = 0): BenchmarkResult
    {
        $iterations = max(1, $iterations);
        $warmup = max(0, $warmup);

        for ($i = 0; $i < $warmup; $i++) {
            $operation();
        }

        gc_collect_cycles();
        $memBefore = memory_get_usage(true);

        $times = [];
        $timesCount = 0;
        $memoryDeltas = [];

        if ($onProgress !== null) {
            $onProgress(0, $iterations);
        }

        if ($progressEvery <= 0) {
            $progressEvery = max(1, (int) floor($iterations / 25));
        }

        for ($i = 0; $i < $iterations; $i++) {
            $iterMemBefore = memory_get_usage(true);
            $start = hrtime(true);
            $operation();
            $end = hrtime(true);
            $iterMemAfter = memory_get_usage(true);

            $times[] = ($end - $start) / 1e6;
            $timesCount++;
            $memoryDeltas[] = max(0, $iterMemAfter - $iterMemBefore);

            if ($onProgress !== null) {
                $done = $i + 1;
                if ($done === $iterations || ($done % $progressEvery) === 0) {
                    $onProgress($done, $iterations);
                }
            }
        }

        $memAfter = memory_get_usage(true);

        $min = min($times);
        $max = max($times);
        $avg = array_sum($times) / max(1, $timesCount);
        $stdDev = $this->standardDeviation($times, $avg);

        $memDeltaBytes = (int) max($memoryDeltas ?: [0]);
        if ($memDeltaBytes === 0) {
            $memDeltaBytes = (int) max(0, $memAfter - $memBefore);
        }

        return new BenchmarkResult(
            min: round($min, 4),
            max: round($max, 4),
            avg: round($avg, 4),
            stdDev: round($stdDev, 4),
            memoryKb: round($memDeltaBytes / 1024, 4),
            memoryBytes: $memDeltaBytes,
            iterations: $iterations,
        );
    }

    /**
     * @param  list<float>  $values
     */
    private function standardDeviation(array $values, float $mean): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }
}
