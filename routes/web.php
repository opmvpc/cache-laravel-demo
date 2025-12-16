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
    Route::post('/all', [BenchmarkController::class, 'runAll'])->name('benchmark.all');
});

Route::prefix('seed')->group(function () {
    Route::get('/', [SeederController::class, 'index'])->name('seed.index');
    Route::post('/', [SeederController::class, 'run'])->name('seed.run');
    Route::get('/status', [SeederController::class, 'status'])->name('seed.status');
});

Route::prefix('export')->group(function () {
    Route::get('/json/{benchmark}', [ExportController::class, 'json'])->name('export.json');
    Route::get('/csv/{benchmark}', [ExportController::class, 'csv'])->name('export.csv');
});
