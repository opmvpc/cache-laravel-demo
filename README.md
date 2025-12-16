# Laravel Cache Benchmark

Small Laravel app to **benchmark and compare caching strategies**:

- Cache store drivers: `file`, `database`, `redis`
- SQL queries: direct vs `Cache::remember()` (hit/miss)
- Fibonacci: naive vs memoized vs iterative
- Cached payload size impact (1KB → 1MB)

## Requirements

- PHP 8.2+
- A database (SQLite works for local dev; MySQL is fine too)
- Redis (optional, only for the Redis benchmark)

## Setup

```bash
composer install
php artisan key:generate
php artisan migrate
```

## Seed data

CLI command:

```bash
php artisan benchmark:seed 1000 --fresh
```

Options:

- `--fresh` truncates tables first
- `--chunk=500` inserts in chunks

You can also seed via the UI at `/seed`.

## Run

- Dashboard: `/`
- Seeder UI: `/seed`

Endpoints (AJAX):

- `POST /benchmark/drivers`
- `POST /benchmark/sql`
- `POST /benchmark/fibonacci`
- `POST /benchmark/datasize`
- `POST /benchmark/all`

Exports (last run stored in file cache):

- `GET /export/json/{benchmark}`
- `GET /export/csv/{benchmark}`
