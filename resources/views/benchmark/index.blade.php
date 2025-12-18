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
	                        <div class="mb-1 text-xs text-slate-400">Dataset size (SQL limit)</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([100, 1000, 10000, 50000] as $size)
                                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm">
                                    <input type="radio" name="dataset_size" value="{{ $size }}" @checked($size === $defaultDatasetSize) />
                                    <span>{{ number_format($size) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-2 text-xs text-slate-500">Filtre les requêtes SQL sur ~N articles. Pour tester 10k/50k, seed au moins autant via <a class="underline hover:text-slate-300" href="{{ route('seed.index') }}">/seed</a>.</div>
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
                <div class="mt-4 text-xs text-slate-500">Compare file/database/redis • cached_miss = 1er appel (cache vide) • cached_hit = cache rempli • Variants: simple, relations, aggregations, complex</div>
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

        <div id="status" class="hidden rounded-xl border border-slate-800 bg-slate-950 p-4 text-sm"></div>
        <div id="progressWrap" class="hidden rounded-xl border border-slate-800 bg-slate-900/40 p-4">
            <div class="mb-2 flex items-center justify-between text-xs text-slate-400">
                <div id="progressMsg">—</div>
                <div id="progressPct">0%</div>
            </div>
            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-950 ring-1 ring-slate-800">
                <div id="progressBar" class="h-3 w-0 bg-indigo-500"></div>
            </div>
        </div>

        <div class="grid gap-6">
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div class="text-base font-semibold">Résultats</div>
                    <div id="exports" class="flex flex-wrap gap-2 text-xs"></div>
                </div>
	                <div class="mt-4 grid gap-6">
	                    <div class="min-w-0">
                        <div class="mb-1 text-sm font-semibold text-slate-200" id="resultTitle">Graphiques</div>
                        <div class="mb-3 text-xs text-slate-500" id="resultDesc">Lance un benchmark pour afficher les résultats.</div>
		                        <div id="chartsGrid" class="grid w-full min-w-0 gap-4 md:grid-cols-2">
			                            <div id="chartAWrap" class="relative flex h-80 w-full min-w-0 flex-col overflow-hidden rounded-lg border border-slate-800 bg-slate-950 p-3">
			                                <div id="chartATitle" class="mb-2 text-xs font-semibold text-slate-200"></div>
			                                <canvas id="chartA" class="w-full flex-1" style="max-width: 100%; min-height: 0;"></canvas>
			                            </div>
			                            <div id="chartBWrap" class="relative flex h-80 w-full min-w-0 flex-col overflow-hidden rounded-lg border border-slate-800 bg-slate-950 p-3">
			                                <div id="chartBTitle" class="mb-2 text-xs font-semibold text-slate-200"></div>
			                                <canvas id="chartB" class="w-full flex-1" style="max-width: 100%; min-height: 0;"></canvas>
			                            </div>
		                        </div>
                    </div>
	                    <div class="min-w-0">
                        <div class="mb-2 text-sm font-semibold text-slate-200">Détails</div>
	                        <div id="details" class="w-full min-w-0 overflow-x-auto text-sm text-slate-200"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const statusEl = document.getElementById('status');
        const progressWrap = document.getElementById('progressWrap');
        const progressBar = document.getElementById('progressBar');
        const progressPct = document.getElementById('progressPct');
	        const progressMsg = document.getElementById('progressMsg');
	        const resultTitleEl = document.getElementById('resultTitle');
		        const resultDescEl = document.getElementById('resultDesc');
		        const chartATitleEl = document.getElementById('chartATitle');
		        const chartBTitleEl = document.getElementById('chartBTitle');
		        const chartsGridEl = document.getElementById('chartsGrid');
		        const chartAWrapEl = document.getElementById('chartAWrap');
		        const chartBWrapEl = document.getElementById('chartBWrap');
		        const detailsEl = document.getElementById('details');
		        const exportsEl = document.getElementById('exports');

        let chartA = null;
        let chartB = null;
        let source = null;

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

        function setProgress(percent, message) {
            progressWrap.classList.remove('hidden');
            progressBar.style.width = Math.max(0, Math.min(100, percent)) + '%';
            progressPct.textContent = Math.max(0, Math.min(100, percent)) + '%';
            progressMsg.textContent = message || '...';
        }

        function clearUi() {
            detailsEl.innerHTML = '';
            exportsEl.innerHTML = '';
            resultTitleEl.textContent = 'Graphiques';
            resultDescEl.textContent = 'Lance un benchmark pour afficher les résultats.';
            if (chartA) { chartA.destroy(); chartA = null; }
            if (chartB) { chartB.destroy(); chartB = null; }
            if (source) { source.close(); source = null; }
		            progressWrap.classList.add('hidden');
		            if (chartATitleEl) chartATitleEl.textContent = '';
		            if (chartBTitleEl) chartBTitleEl.textContent = '';
		            if (chartsGridEl) chartsGridEl.className = 'grid w-full min-w-0 gap-4 md:grid-cols-2';
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
	            if (chartATitleEl) chartATitleEl.textContent = 'Temps par opération (avg ms)';
	            if (chartBTitleEl) chartBTitleEl.textContent = 'Empreinte du cache (KB, store_kb)';
            resultTitleEl.textContent = 'Résultats : Cache Drivers';
            resultDescEl.textContent = 'Compare file/database/redis sur put/get(hit)/get(miss)/forget/remember/flush (ms). Le graphe de droite montre l’empreinte du cache (store_kb): file=taille fichier, database=LENGTH(value), redis=MEMORY USAGE.';

            const drivers = Object.keys(payload.results || {});
            const ops = ['put', 'get_hit', 'get_miss', 'forget', 'remember', 'flush'];

	            const rows = [['metric', 'operation', ...drivers]];
	            for (const op of ops) {
	                const avgRow = ['avg_ms', op];
	                const kbRow = ['store_kb', op];
	                for (const d of drivers) {
	                    avgRow.push(payload.results?.[d]?.[op]?.avg ?? payload.results?.[d]?.error ?? 'n/a');
	                    kbRow.push(payload.results?.[d]?.[op]?.store_kb ?? payload.results?.[d]?.[op]?.memory_kb ?? payload.results?.[d]?.error ?? 'n/a');
	                }
	                rows.push(avgRow);
	                rows.push(kbRow);
	            }
	            const extra = `
	                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
	                    <div class="font-semibold text-slate-200">Note sur la mesure "memoire"</div>
	                    <div class="mt-1 text-slate-400">
	                        <div>• Ici, le graphe de droite montre surtout l’empreinte dans le store (<span class="font-mono">store_kb</span>) pour rendre la difference visible.</div>
	                        <div>• Le payload stocke fait ~10KB; <span class="font-mono">remember</span> utilise des cles differentes a chaque iteration (donc l’empreinte augmente avec N).</div>
	                    </div>
	                </div>
	            `;
            const explanation = `
                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
                    <div class="font-semibold text-slate-200">Ce qui est mesuré</div>
                    <div class="mt-1 text-slate-400">
                        <div>• Chaque opération est répétée N fois (itérations) et on calcule min/max/moyenne/écart-type.</div>
                        <div>• Le graphique de droite (<span class="font-mono">store_kb</span>) est l’empreinte dans le store: file=taille du fichier, database=LENGTH(value), redis=MEMORY USAGE.</div>
                    </div>
                </div>
            `;
	            detailsEl.innerHTML = extra + explanation + renderTable(rows);

            const datasets = drivers.map((d, i) => ({
                label: d,
                data: ops.map(op => payload.results?.[d]?.[op]?.avg ?? null),
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: ops, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'avg (ms)', color: '#94a3b8' } },
                    },
                }
            });

            const memDatasets = drivers.map(d => ({
                label: d,
                data: ops.map(op => payload.results?.[d]?.[op]?.store_kb ?? payload.results?.[d]?.[op]?.memory_kb ?? null),
            }));

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: { labels: ops, datasets: memDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'cache footprint (KB)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderSql(payload) {
	            exportsEl.innerHTML = linkExport('sql_queries');
	            if (chartATitleEl) chartATitleEl.textContent = 'Latence (avg ms): direct vs cache hit';
	            if (chartBTitleEl) chartBTitleEl.textContent = 'Speedup vs direct (x): direct / cached_hit';
            resultTitleEl.textContent = 'Résultats : SQL Queries';
            resultDescEl.textContent = 'Compare no-cache (direct) vs cache stores (file/database/redis) en miss/hit. Speedup (x) = direct / cached_hit.';
		            if (chartsGridEl) chartsGridEl.className = 'grid w-full min-w-0 gap-4 md:grid-cols-1';

	            const variants = payload.results?.variants || {};
	            const names = Object.keys(variants);
	            const stores = payload.results?.cache_stores || ['file', 'database', 'redis'];
	            const requestedSize = payload?.config?.dataset_size ?? null;
	            const dbCount = payload?.results?.db_article_count ?? null;
	            const effectiveSize = payload?.results?.effective_dataset_size ?? null;
	            const sizeInfo = (effectiveSize !== null && dbCount !== null)
	                ? ('Dataset SQL: ' + effectiveSize + ' (DB: ' + dbCount + (requestedSize !== null ? ', demandé: ' + requestedSize : '') + ')')
	                : null;
	            resultDescEl.textContent = ('Compare no-cache (direct) vs Cache::store(file|database|redis)->remember() en miss/hit. ' + (sizeInfo ?? '')).trim();

	            const rows = [
	                [
	                    'variant',
	                    'direct_ms',
	                    'direct_dbq',
	                    ...stores.flatMap(s => [`${s}_hit_ms`, `${s}_hit_dbq`, `${s}_speedup_x`, `${s}_gain_%`, `${s}_miss_ms`, `${s}_miss_dbq`]),
	                ],
	            ];

	            for (const name of names) {
	                const direct = variants[name]?.direct?.avg ?? null;
	                const directDbq = variants[name]?.direct?.db_queries ?? null;

	                const storeCells = stores.flatMap(s => {
	                    const store = variants[name]?.stores?.[s] ?? null;
	                    if (store?.error) {
	                        return ['error', '', '', '', '', ''];
	                    }

	                    const hit = store?.cached_hit?.avg ?? null;
	                    const hitDbq = store?.cached_hit?.db_queries ?? null;
	                    const miss = store?.cached_miss?.avg ?? null;
	                    const missDbq = store?.cached_miss?.db_queries ?? null;

	                    const speedup = (direct && hit) ? (direct / hit) : null;
	                    const gainPct = (direct && hit) ? ((direct - hit) / direct) * 100 : null;

	                    return [
	                        hit ?? 'n/a',
	                        hitDbq ?? '',
	                        speedup ? speedup.toFixed(2) : 'n/a',
	                        gainPct !== null ? gainPct.toFixed(1) : 'n/a',
	                        miss ?? 'n/a',
	                        missDbq ?? '',
	                    ];
	                });

	                rows.push([name, direct ?? 'n/a', directDbq ?? '', ...storeCells]);
	            }

	            const speedupsByStore = Object.fromEntries(stores.map(s => [s, []]));
	            for (const name of names) {
	                const direct = variants[name]?.direct?.avg ?? null;
	                for (const s of stores) {
	                    const hit = variants[name]?.stores?.[s]?.cached_hit?.avg ?? null;
	                    const v = (direct && hit) ? (direct / hit) : null;
	                    if (v !== null) speedupsByStore[s].push(v);
	                }
	            }
	            const speedupSummary = stores
	                .map(s => {
	                    const arr = speedupsByStore[s] || [];
	                    if (!arr.length) return `${s}: n/a`;
	                    const avg = arr.reduce((a, b) => a + b, 0) / arr.length;
	                    return `${s}: ${avg.toFixed(2)}x`;
	                })
	                .join(' · ');

	            const moreExplanation = `
	                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
	                    <div class="font-semibold text-slate-200">Pourquoi ce benchmark est pedagogique</div>
	                    <div class="mt-1 text-slate-400">
	                        <div>• Chaque variant mesure la meme requete en 3 situations: <span class="font-mono">direct</span> (no-cache), <span class="font-mono">cached_miss</span> (1er remember()), <span class="font-mono">cached_hit</span> (cache rempli).</div>
	                        <div>• On compare ensuite plusieurs stores: <span class="font-mono">file</span>, <span class="font-mono">database</span>, <span class="font-mono">redis</span>.</div>
	                        <div>• <span class="font-mono">dbq</span> = nombre de requetes SQL executees sur la connexion DB (inclut aussi la table <span class="font-mono">cache</span> pour le store database).</div>
	                        <div>• <span class="font-mono">speedup_x</span> = <span class="font-mono">direct_ms / hit_ms</span> (1 = egal, &gt;1 = plus rapide).</div>
	                        <div class="mt-1">Moyenne des speedups (cached_hit) par store: <span class="font-mono">${speedupSummary}</span></div>
	                    </div>
	                </div>
	            `;

	            const explanation = `
	                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
	                    <div class="font-semibold text-slate-200">Comment lire ce benchmark</div>
	                    <div class="mt-1 text-slate-400">
                        <div>• <span class="font-mono">direct</span> = requête SQL exécutée à chaque itération (baseline).</div>
                        <div>• <span class="font-mono">cached_miss</span> = 1er <span class="font-mono">remember()</span> (cache vide) → requête + écriture en cache.</div>
                        <div>• <span class="font-mono">cached_hit</span> = cache rempli → lecture depuis le store (file/database/redis), sans requête.</div>
                        <div>• <span class="font-mono">speedup_x</span> = <span class="font-mono">direct_avg_ms / cached_hit_ms</span> (plus grand = mieux).</div>
	                    </div>
	                </div>
	            `;
	            detailsEl.innerHTML = moreExplanation + explanation + renderTable(rows);

	            const datasets = [
	                { label: 'direct (no-cache)', data: names.map(n => variants[n]?.direct?.avg ?? null) },
	                ...stores.map(s => ({ label: `${s} (cached_hit)`, data: names.map(n => variants[n]?.stores?.[s]?.cached_hit?.avg ?? null) })),
	            ];

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: names, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'avg (ms)', color: '#94a3b8' } },
                    },
                }
            });

	            const speedupDatasets = stores.map(s => ({
	                label: `${s}`,
	                data: names.map(n => {
	                    const direct = variants[n]?.direct?.avg ?? null;
	                    const hit = variants[n]?.stores?.[s]?.cached_hit?.avg ?? null;
	                    return (direct && hit) ? (direct / hit) : null;
	                }),
            }));

            chartB = new Chart(document.getElementById('chartB'), {
                type: 'bar',
                data: { labels: names, datasets: speedupDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'speedup vs direct (x)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function renderFibonacci(payload) {
	            exportsEl.innerHTML = linkExport('fibonacci');
	            if (chartATitleEl) chartATitleEl.textContent = 'Temps (ms) par n';
	            if (chartBTitleEl) chartBTitleEl.textContent = 'Nombre d’appels récursifs';
            resultTitleEl.textContent = 'Résultats : Fibonacci';
            resultDescEl.textContent = 'Compare naive (O(2^n)), memoized (O(n)), iterative (O(n)). Le graphe de droite montre le nombre d’appels récursifs.';

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
            const explanation = `
                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
                    <div class="font-semibold text-slate-200">Idée pédagogique</div>
                    <div class="mt-1 text-slate-400">
                        <div>• Naive explose en nombre d’appels (≈ 2^n) → très lent.</div>
                        <div>• Mémoïsation évite de recalculer les sous-problèmes → beaucoup moins d’appels.</div>
                    </div>
                </div>
            `;
            detailsEl.innerHTML = explanation + renderTable(rows);

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
                    maintainAspectRatio: false,
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
                    maintainAspectRatio: false,
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
	            if (chartATitleEl) chartATitleEl.textContent = 'Temps (avg ms): put/get par taille';
	            if (chartBTitleEl) chartBTitleEl.textContent = 'Delta heap PHP (KB)';
            resultTitleEl.textContent = 'Résultats : Data Size';
            resultDescEl.textContent = 'Mesure put/get pour 1KB → 1MB sur le store choisi (ms + delta de heap PHP).';

            const results = payload.results?.results || {};
            const sizes = Object.keys(results);
            const ops = ['put', 'get'];

            const rows = [['size', 'bytes', 'put_avg_ms', 'get_avg_ms']];
            for (const s of sizes) {
                rows.push([s, results[s]?.bytes ?? '', results[s]?.put?.avg ?? 'n/a', results[s]?.get?.avg ?? 'n/a']);
            }
            const explanation = `
                <div class="mb-3 rounded-lg border border-slate-800 bg-slate-950 p-3 text-xs text-slate-300">
                    <div class="font-semibold text-slate-200">Ce qui change ici</div>
                    <div class="mt-1 text-slate-400">
                        <div>• Même code, même store → seule la taille de la donnée (1KB → 1MB) varie.</div>
                        <div>• Utile pour visualiser l’impact de la sérialisation + I/O réseau/disque.</div>
                    </div>
                </div>
            `;
            detailsEl.innerHTML = explanation + renderTable(rows);

            const datasets = ops.map(op => ({
                label: op,
                data: sizes.map(s => results[s]?.[op]?.avg ?? null),
            }));

            chartA = new Chart(document.getElementById('chartA'), {
                type: 'bar',
                data: { labels: sizes, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
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
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: '#cbd5e1' } } },
                    scales: {
                        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' } },
                        y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.15)' }, title: { display: true, text: 'PHP heap Δ (KB)', color: '#94a3b8' } },
                    },
                }
            });
        }

        function stream(url, onResult) {
            if (source) source.close();
            setProgress(0, 'Connecting...');

            source = new EventSource(url);
            source.addEventListener('progress', (e) => {
                const p = JSON.parse(e.data || '{}');
                setProgress(p.percent ?? 0, p.message ?? '...');
            });
            source.addEventListener('result', (e) => {
                const payload = JSON.parse(e.data || '{}');
                source.close();
                source = null;
                onResult(payload);
            });
            source.addEventListener('server_error', (e) => {
                const data = JSON.parse(e.data || '{}');
                setStatus(data.message || 'Erreur', 'error');
                source.close();
                source = null;
            });
            source.onerror = () => {
                // connection errors also come here; keep UI message from server_error if any
            };
        }

        function run(kind) {
            const cfg = getConfig();
            clearUi();
            setStatus('Benchmark en cours...');

            try {
                if (kind === 'drivers') {
                    stream(`{{ route('benchmark.stream.drivers') }}?iterations=${encodeURIComponent(cfg.iterations)}`, (payload) => {
                        setStatus(`OK • ${payload.benchmark} • ${payload.timestamp}`);
                        renderDrivers(payload);
                    });
                }
                if (kind === 'sql') {
                    const it = Math.min(250, cfg.iterations);
                    stream(`{{ route('benchmark.stream.sql') }}?iterations=${encodeURIComponent(it)}&dataset_size=${encodeURIComponent(cfg.datasetSize)}`, (payload) => {
                        const dbCount = payload?.results?.db_article_count ?? null;
                        const eff = payload?.results?.effective_dataset_size ?? null;
                        const requested = payload?.config?.dataset_size ?? cfg.datasetSize;

                        if (dbCount !== null && eff !== null && dbCount < requested) {
                            setStatus(`OK • ${payload.benchmark} • ${payload.timestamp} • ⚠️ dataset=${eff} (DB=${dbCount}, demandé=${requested} → seed plus)`);
                        } else if (dbCount !== null && eff !== null) {
                            setStatus(`OK • ${payload.benchmark} • ${payload.timestamp} • dataset=${eff} (DB=${dbCount})`);
                        } else {
                            setStatus(`OK • ${payload.benchmark} • ${payload.timestamp}`);
                        }
                        renderSql(payload);
                    });
                }
                if (kind === 'fibonacci') {
                    stream(`{{ route('benchmark.stream.fibonacci') }}`, (payload) => {
                        setStatus(`OK • ${payload.benchmark} • ${payload.timestamp}`);
                        renderFibonacci(payload);
                    });
                }
                if (kind === 'datasize') {
                    stream(`{{ route('benchmark.stream.datasize') }}?iterations=${encodeURIComponent(cfg.iterations)}&driver=${encodeURIComponent(cfg.dataSizeDriver)}`, (payload) => {
                        setStatus(`OK • ${payload.benchmark} • ${payload.timestamp}`);
                        renderDataSize(payload);
                    });
                }
            } catch (e) {
                setStatus(e.message || 'Erreur', 'error');
            }
        }

        document.querySelectorAll('button[data-run]').forEach(btn => {
            btn.addEventListener('click', () => run(btn.getAttribute('data-run')));
        });
    </script>
</x-layouts.app>
