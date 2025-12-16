<?php

use App\Services\Benchmark\FibonacciBenchmark;

test('fibonacci memoized matches iterative', function () {
    $benchmark = new FibonacciBenchmark();
    $results = $benchmark->run();

    foreach ($results['cases'] as $case) {
        expect($case['memoized']['avg'])->toBeNumeric();
        expect($case['iterative']['avg'])->toBeNumeric();
        expect($case['value'])->toBeInt();
    }
});

