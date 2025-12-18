<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Cache Benchmark') }}</title>

        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    </head>
	    <body class="bg-slate-950 text-slate-100 overflow-y-scroll overflow-x-hidden">
        <header class="border-b border-slate-800 bg-slate-950/70 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-lg bg-indigo-500/15 text-lg ring-1 ring-indigo-400/30">🗄️</div>
                    <div>
                        <div class="text-lg font-semibold leading-tight">Laravel Cache Benchmark</div>
                        <div class="text-xs text-slate-400">Drivers, SQL, Fibonacci, data size</div>
                    </div>
                </div>
                <nav class="flex items-center gap-3 text-sm">
                    <a class="rounded-md px-3 py-2 text-slate-300 hover:bg-slate-900 hover:text-white" href="{{ route('benchmark.index') }}">Dashboard</a>
                    <a class="rounded-md px-3 py-2 text-slate-300 hover:bg-slate-900 hover:text-white" href="{{ route('seed.index') }}">Seeder</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-slate-500">
            <div class="border-t border-slate-800 pt-6">
                Cache store default: <span class="font-mono">{{ config('cache.default') }}</span>
            </div>
        </footer>
    </body>
</html>
