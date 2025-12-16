<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('benchmark dashboard loads', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Laravel Cache Benchmark');
});

test('drivers benchmark returns expected structure', function () {
    $this->postJson('/benchmark/drivers', ['iterations' => 2])
        ->assertOk()
        ->assertJsonStructure([
            'benchmark',
            'timestamp',
            'config' => ['iterations'],
            'results' => ['file', 'database', 'redis'],
            'winner',
        ]);
});

test('fibonacci benchmark returns cases', function () {
    $this->postJson('/benchmark/fibonacci')
        ->assertOk()
        ->assertJsonStructure([
            'benchmark',
            'results' => [
                'cases' => [
                    '*' => ['n', 'value', 'naive', 'memoized', 'iterative'],
                ],
            ],
        ]);
});

