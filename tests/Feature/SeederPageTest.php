<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder page loads', function () {
    $this->get('/seed')
        ->assertOk()
        ->assertSee('Database Seeder');
});

