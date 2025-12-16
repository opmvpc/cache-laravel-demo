<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SeederController;

Route::get('/', [BenchmarkController::class, 'index'])->name('benchmark.index');

Route::prefix('benchmark')->group(function () {
    Route::post('/drivers', [BenchmarkController::class, 'runDrivers'])->name('benchmark.drivers');
    Route::post('/sql', [BenchmarkController::class, 'runSql'])->name('benchmark.sql');
    Route::post('/fibonacci', [BenchmarkController::class, 'runFibonacci'])->name('benchmark.fibonacci');
    Route::post('/datasize', [BenchmarkController::class, 'runDataSize'])->name('benchmark.datasize');

    Route::get('/stream/drivers', [BenchmarkController::class, 'streamDrivers'])->name('benchmark.stream.drivers');
    Route::get('/stream/sql', [BenchmarkController::class, 'streamSql'])->name('benchmark.stream.sql');
    Route::get('/stream/fibonacci', [BenchmarkController::class, 'streamFibonacci'])->name('benchmark.stream.fibonacci');
    Route::get('/stream/datasize', [BenchmarkController::class, 'streamDataSize'])->name('benchmark.stream.datasize');
});

Route::prefix('seed')->group(function () {
    Route::get('/', [SeederController::class, 'index'])->name('seed.index');
    Route::post('/', [SeederController::class, 'run'])->name('seed.run');
    Route::get('/status', [SeederController::class, 'status'])->name('seed.status');
    Route::get('/stream', [SeederController::class, 'stream'])->name('seed.stream');
});

Route::prefix('export')->group(function () {
    Route::get('/json/{benchmark}', [ExportController::class, 'json'])->name('export.json');
    Route::get('/csv/{benchmark}', [ExportController::class, 'csv'])->name('export.csv');
});
