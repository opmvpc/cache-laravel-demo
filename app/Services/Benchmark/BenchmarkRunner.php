<?php

namespace App\Services\Benchmark;

use Closure;

class BenchmarkRunner
{
    /**
     * @param  callable(): void  $operation
     */
    public function run(callable $operation, int $iterations = 100, int $warmup = 2): BenchmarkResult
    {
        $iterations = max(1, $iterations);
        $warmup = max(0, $warmup);

        for ($i = 0; $i < $warmup; $i++) {
            $operation();
        }

        gc_collect_cycles();
        $peakBefore = memory_get_peak_usage(true);

        $times = [];
        $timesCount = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $operation();
            $end = hrtime(true);

            $times[] = ($end - $start) / 1e6;
            $timesCount++;
        }

        $peakAfter = memory_get_peak_usage(true);

        $min = min($times);
        $max = max($times);
        $avg = array_sum($times) / max(1, $timesCount);
        $stdDev = $this->standardDeviation($times, $avg);

        return new BenchmarkResult(
            min: round($min, 4),
            max: round($max, 4),
            avg: round($avg, 4),
            stdDev: round($stdDev, 4),
            memoryKb: (int) max(0, ($peakAfter - $peakBefore) / 1024),
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

