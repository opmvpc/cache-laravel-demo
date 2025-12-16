<?php

namespace App\Services\Benchmark;

class BenchmarkResult
{
    public function __construct(
        public readonly float $min,
        public readonly float $max,
        public readonly float $avg,
        public readonly float $stdDev,
        public readonly float $memoryKb,
        public readonly int $memoryBytes,
        public readonly int $iterations,
        public readonly string $unit = 'ms',
    ) {
    }

    /**
     * @return array{min: float, max: float, avg: float, std_dev: float, memory_kb: float, memory_bytes: int, iterations: int, unit: string}
     */
    public function toArray(): array
    {
        return [
            'min' => $this->min,
            'max' => $this->max,
            'avg' => $this->avg,
            'std_dev' => $this->stdDev,
            'memory_kb' => $this->memoryKb,
            'memory_bytes' => $this->memoryBytes,
            'iterations' => $this->iterations,
            'unit' => $this->unit,
        ];
    }
}
