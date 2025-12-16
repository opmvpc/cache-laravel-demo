<?php

namespace App\Services\Benchmark;

use RuntimeException;

class FibonacciBenchmark
{
    public function __construct(private readonly BenchmarkRunner $runner = new BenchmarkRunner())
    {
    }

    /**
     * @return array{cases: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        $ns = [10, 20, 30, 35, 40];
        $cases = [];

        foreach ($ns as $n) {
            $naiveCalls = 0;
            $memoCalls = 0;

            $naive = null;
            $memo = null;
            $iter = null;

            $naiveResult = null;
            if ($n <= 35) {
                $naiveResult = $this->runner->run(
                    operation: function () use ($n, &$naive, &$naiveCalls) {
                        $naiveCalls = 0;
                        $naive = $this->fibNaive($n, $naiveCalls);
                    },
                    iterations: 1,
                    warmup: 0
                );
            }

            $memoResult = $this->runner->run(
                operation: function () use ($n, &$memo, &$memoCalls) {
                    $memoCalls = 0;
                    $memo = $this->fibMemoized($n, $memoCalls);
                },
                iterations: 1,
                warmup: 0
            );

            $iterResult = $this->runner->run(
                operation: function () use ($n, &$iter) {
                    $iter = $this->fibIterative($n);
                },
                iterations: 1,
                warmup: 0
            );

            if ($naive !== null && $memo !== null && $naive !== $memo) {
                throw new RuntimeException("fibNaive and fibMemoized mismatch for n={$n}");
            }

            if ($memo !== null && $iter !== null && $memo !== $iter) {
                throw new RuntimeException("fibMemoized and fibIterative mismatch for n={$n}");
            }

            $cases[] = [
                'n' => $n,
                'value' => $memo ?? $iter ?? $naive ?? null,
                'naive' => $naiveResult ? array_merge($naiveResult->toArray(), ['calls' => $naiveCalls]) : ['skipped' => true, 'reason' => 'n>35', 'calls' => null],
                'memoized' => array_merge($memoResult->toArray(), ['calls' => $memoCalls]),
                'iterative' => array_merge($iterResult->toArray(), ['calls' => 0]),
            ];
        }

        return [
            'cases' => $cases,
        ];
    }

    private function fibNaive(int $n, int &$calls): int
    {
        $calls++;

        if ($n < 0) {
            throw new RuntimeException('n must be >= 0');
        }

        if ($n <= 1) {
            return $n;
        }

        return $this->fibNaive($n - 1, $calls) + $this->fibNaive($n - 2, $calls);
    }

    private function fibMemoized(int $n, int &$calls, array &$memo = []): int
    {
        $calls++;

        if ($n < 0) {
            throw new RuntimeException('n must be >= 0');
        }

        if ($n <= 1) {
            return $n;
        }

        if (isset($memo[$n])) {
            return $memo[$n];
        }

        $memo[$n] = $this->fibMemoized($n - 1, $calls, $memo) + $this->fibMemoized($n - 2, $calls, $memo);

        return $memo[$n];
    }

    private function fibIterative(int $n): int
    {
        if ($n < 0) {
            throw new RuntimeException('n must be >= 0');
        }

        if ($n <= 1) {
            return $n;
        }

        $a = 0;
        $b = 1;

        for ($i = 2; $i <= $n; $i++) {
            $tmp = $a + $b;
            $a = $b;
            $b = $tmp;
        }

        return $b;
    }
}

