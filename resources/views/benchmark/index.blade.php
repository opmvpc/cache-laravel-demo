<x-layouts.app :title="'Cache Benchmark'">
    <div class="grid gap-6">
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <div class="text-base font-semibold">Configuration globale</div>
                    <div class="text-sm text-slate-400">Utilisée pour les endpoints AJAX.</div>
                </div>
                <div class="flex flex-col gap-3 md:flex-row md:items-end">
                    <label class="block">
                        <div class="mb-1 text-xs text-slate-400">Itérations par test</div>
                        <input id="iterations" type="number" min="1" max="5000" value="{{ $defaultIterations }}" class="w-40 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-indigo-400" />
                    </label>
                    <div>
                        <div class="mb-1 text-xs text-slate-400">Dataset size (SQL)</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([100, 1000, 10000, 50000] as $size)
                                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm">
                                    <input type="radio" name="dataset_size" value="{{ $size }}" @checked($size === $defaultDatasetSize) />
                                    <span>{{ number_format($size) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold">🗄️ Cache Drivers</div>
                        <div class="text-sm text-slate-400">file vs database vs redis</div>
                    </div>
                    <button data-run="drivers" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Lancer</button>
                </div>
                <div class="mt-4 text-xs text-slate-500">Ops: put/get hit/get miss/forget/remember/flush</div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold">🔍 SQL Queries</div>
                        <div class="text-sm text-slate-400">sans cache vs cache (hit/miss)</div>
                    </div>
                    <button data-run="sql" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Lancer</button>
                </div>
                <div class="mt-4 text-xs text-slate-500">Variants: simple, relations, aggregations, complex</div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold">🔢 Fibonacci</div>
                        <div class="text-sm text-slate-400">naive vs memoized vs iterative</div>
                    </div>
                    <button data-run="fibonacci" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Lancer</button>
                </div>
                <div class="mt-4 text-xs text-slate-500">fib(10,20,30,35,40) • naive limité à 35</div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold">📊 Data Size</div>
                        <div class="text-sm text-slate-400">1KB → 1MB (put/get)</div>
                    </div>
                    <button data-run="datasize" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Lancer</button>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                    Driver:
                    <select id="datasize_driver" class="rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-xs">
                        <option value="file">file</option>
                        <option value="database">database</option>
                        <option value="redis">redis</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-base font-semibold">▶️ Tout lancer</div>
                    <div class="text-sm text-slate-400">Exécute les 4 catégories (SQL avec itérations réduites).</div>
                </div>
                <button data-run="all" class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400">Lancer TOUS les benchmarks</button>
            </div>
        </div>

        <div id="status" class="hidden rounded-xl border border-slate-800 bg-slate-950 p-4 text-sm"></div>

        <div class="grid gap-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-base font-semibold">Résultats</div>
                    <div id="exports" class="flex flex-wrap gap-2 text-xs"></div>
                </div>
                <div class="mt-4 grid gap-6">
                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-200">Graphiques</div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <canvas id="chartA" height="140"></canvas>
                            <canvas id="chartB" height="140"></canvas>
                        </div>
                    </div>
                    <div>
                        <div class="mb-2 text-sm font-semibold text-slate-200">Détails</div>
                        <div id="details" class="overflow-x-auto text-sm text-slate-200"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const statusEl = document.getElementById('status');
        const detailsEl = document.getElementById('details');
        const exportsEl = document.getElementById('exports');

        let chartA = null;
        let chartB = null;

        function getConfig() {
            const iterations = parseInt(document.getElementById('iterations').value || '100', 10);
            const datasetSize = parseInt(document.querySelector('input[name="dataset_size"]:checked')?.value || '1000', 10);
            const dataSizeDriver = document.getElementById('datasize_driver').value || 'file';
            return { iterations, datasetSize, dataSizeDriver };
        }

        function setStatus(message, kind = 'info') {
            statusEl.classList.remove('hidden');
            statusEl.className = 'rounded-xl border p-4 text-sm';
            if (kind === 'error') statusEl.classList.add('border-rose-600/40', 'bg-rose-950/40', 'text-rose-200');
            else statusEl.classList.add('border-slate-800', 'bg-slate-950', 'text-slate-200');
            statusEl.textContent = message;
        }

        function clearUi() {
            detailsEl.innerHTML = '';
            exportsEl.innerHTML = '';
            if (chartA) { chartA.destroy(); chartA = null; }
            if (chartB) { chartB.destroy(); chartB = null; }
        }

        async function postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body || {}),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data?.message || data?.error || 'Request failed';
                throw new Error(msg);
            }
            return data;
        }

        function linkExport(key) {
            return `
                <a class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 hover:bg-slate-900" href="/export/json/${key}" target="_blank">📥 JSON</a>
                <a class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 hover:bg-slate-900" href="/export/csv/${key}" target="_blank">📥 CSV</a>
            `;
        }

        function renderTable(rows) {
            const header = rows[0];
            const body = rows.slice(1);
            return `
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr>
                            ${header.map(h => `<th class="border-b border-slate-800 px-3 py-2 text-xs font-semibold text-slate-400">${h}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${body.map(r => `
                            <tr class="hover:bg-slate-950/60">
                                ${r.map(c => `<td class="border-b border-slate-900 px-3 py-2 font-mono text-xs">${c ?? ''}</td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function fmtMs(x) {
            if (x === null || x === undefined) return '';
            return Number(x).toFixed(4);
        }

        function renderDrivers(payload) {
            exportsEl.innerHTML = linkExport('cache_drivers');

            const drivers = Object.keys(payload.results || {});
            const ops = ['put', 'get_hit', 'get_miss', 'forget', 'remember', 'flush'];

            const rows = [['operation', ...drivers]];
            for (const op of ops) {
                const row = [op];
                for (const d of drivers) row.push(payload.results?.[d]?.[op]?.avg ?? payload.results?.[d]?.error ?? 'n/a');
                rows.push(row);
            }
            detailsEl.innerHTML = renderTable(rows);

            const datasets = drivers.map((d, i) => ({
                label: d,
                data: ops.map(op => payload.results?.[d]?.[op]?.avg ?? null),
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: ops, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'avg (ms)', color: '#94a3b8' } },
                    },
                }
            });

            const memDatasets = drivers.map(d => ({
                label: d,
                data: ops.map(op => payload.results?.[d]?.[op]?.memory_kb ?? null),
            }));

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: { labels: ops, datasets: memDatasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'memory (KB)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderSql(payload) {
            exportsEl.innerHTML = linkExport('sql_queries');

            const variants = payload.results?.variants || {};
            const names = Object.keys(variants);
            const modes = ['direct', 'cached_miss', 'cached_hit'];

            const rows = [['variant', ...modes]];
            for (const name of names) {
                rows.push([name, ...modes.map(m => variants[name]?.[m]?.avg ?? 'n/a')]);
            }
            detailsEl.innerHTML = renderTable(rows);

            const datasets = modes.map(mode => ({
                label: mode,
                data: names.map(n => variants[n]?.[mode]?.avg ?? null),
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: names, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'avg (ms)', color: '#94a3b8' } },
                    },
                }
            });

            const memDatasets = modes.map(mode => ({
                label: mode,
                data: names.map(n => variants[n]?.[mode]?.memory_kb ?? null),
            }));

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: { labels: names, datasets: memDatasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'memory (KB)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderFibonacci(payload) {
            exportsEl.innerHTML = linkExport('fibonacci');

            const cases = payload.results?.cases || [];
            const labels = cases.map(c => `fib(${c.n})`);
            const methods = ['naive', 'memoized', 'iterative'];

            const rows = [['n', 'value', ...methods.map(m => `${m}_avg_ms`), ...methods.map(m => `${m}_calls`)]];
            for (const c of cases) {
                rows.push([
                    c.n,
                    c.value,
                    c.naive?.avg ?? (c.naive?.skipped ? 'skipped' : 'n/a'),
                    c.memoized?.avg ?? 'n/a',
                    c.iterative?.avg ?? 'n/a',
                    c.naive?.calls ?? '',
                    c.memoized?.calls ?? '',
                    c.iterative?.calls ?? '',
                ]);
            }
            detailsEl.innerHTML = renderTable(rows);

            const datasets = methods.map(method => ({
                label: method,
                data: cases.map(c => c?.[method]?.avg ?? null),
                spanGaps: true,
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'time (ms)', color: '#94a3b8' } },
                    },
                }
            });

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: {
                    labels: cases.map(c => c.n),
                    datasets: [
                        { label: 'naive calls', data: cases.map(c => c.naive?.calls ?? null) },
                        { label: 'memoized calls', data: cases.map(c => c.memoized?.calls ?? null) },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'n', color: '#94a3b8' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'calls', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderDataSize(payload) {
            exportsEl.innerHTML = linkExport('data_size');

            const results = payload.results?.results || {};
            const sizes = Object.keys(results);
            const ops = ['put', 'get'];

            const rows = [['size', 'bytes', 'put_avg_ms', 'get_avg_ms']];
            for (const s of sizes) {
                rows.push([s, results[s]?.bytes ?? '', results[s]?.put?.avg ?? 'n/a', results[s]?.get?.avg ?? 'n/a']);
            }
            detailsEl.innerHTML = renderTable(rows);

            const datasets = ops.map(op => ({
                label: op,
                data: sizes.map(s => results[s]?.[op]?.avg ?? null),
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: sizes, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'avg (ms)', color: '#94a3b8' } },
                    },
                }
            });

            const memDatasets = ops.map(op => ({
                label: op + ' memory_kb',
                data: sizes.map(s => results[s]?.[op]?.memory_kb ?? null),
            }));

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: { labels: sizes, datasets: memDatasets },
                options: {
                    responsive: true,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'memory (KB)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderAll(payload) {
            exportsEl.innerHTML = linkExport('all');
            const rows = [['benchmark', 'note']];
            for (const key of Object.keys(payload.results || {})) {
                rows.push([key, 'See /export/json/all']);
            }
            detailsEl.innerHTML = renderTable(rows);
        }

        async function run(kind) {
            const cfg = getConfig();
            clearUi();
            setStatus('Benchmark en cours...');

            try {
                let payload = null;

                if (kind === 'drivers') payload = await postJson('{{ route('benchmark.drivers') }}', { iterations: cfg.iterations });
                if (kind === 'sql') payload = await postJson('{{ route('benchmark.sql') }}', { iterations: Math.min(250, cfg.iterations), dataset_size: cfg.datasetSize });
                if (kind === 'fibonacci') payload = await postJson('{{ route('benchmark.fibonacci') }}', {});
                if (kind === 'datasize') payload = await postJson('{{ route('benchmark.datasize') }}', { iterations: cfg.iterations, driver: cfg.dataSizeDriver });
                if (kind === 'all') payload = await postJson('{{ route('benchmark.all') }}', { iterations: cfg.iterations, dataset_size: cfg.datasetSize });

                setStatus(`OK • ${payload.benchmark} • ${payload.timestamp}`);

                if (kind === 'drivers') renderDrivers(payload);
                if (kind === 'sql') renderSql(payload);
                if (kind === 'fibonacci') renderFibonacci(payload);
                if (kind === 'datasize') renderDataSize(payload);
                if (kind === 'all') renderAll(payload);
            } catch (e) {
                setStatus(e.message || 'Erreur', 'error');
            }
        }

        document.querySelectorAll('button[data-run]').forEach(btn => {
            btn.addEventListener('click', () => run(btn.getAttribute('data-run')));
        });
    </script>
</x-layouts.app>
