<x-layouts.app :title="'Seeder'">
    <div class="grid gap-6">
        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-base font-semibold">🌱 Database Seeder</div>
                    <div class="text-sm text-slate-400">Lance <span class="font-mono">php artisan benchmark:seed</span> en arrière-plan.</div>
                </div>
                <a class="rounded-lg border border-slate-700 bg-slate-950 px-4 py-2 text-sm hover:bg-slate-900" href="{{ route('benchmark.index') }}">← Retour</a>
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="text-sm font-semibold text-slate-200">État actuel</div>
            <div id="counts_grid" class="mt-3 grid gap-2 text-sm text-slate-300 md:grid-cols-3">
                @foreach ($counts as $k => $v)
                    <div class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $k }}</div>
                        <div class="mt-1 font-mono text-lg" data-count="{{ $k }}">{{ number_format($v) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="text-sm font-semibold text-slate-200">Générer des données</div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Nombre d'articles</div>
                    <input id="seed_count" type="number" min="1" max="200000" value="1000" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Authors (optionnel)</div>
                    <input id="seed_authors" type="number" min="1" max="50000" placeholder="auto" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Categories (optionnel)</div>
                    <input id="seed_categories" type="number" min="1" max="5000" placeholder="auto" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Tags (optionnel)</div>
                    <input id="seed_tags" type="number" min="1" max="20000" placeholder="auto" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Comments / article (moy.)</div>
                    <input id="seed_comments" type="number" min="0" max="50" step="0.5" value="3" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-4 py-3 text-sm">
                    <input id="seed_fresh" type="checkbox" />
                    <span>Vider les tables avant</span>
                </label>
                <div class="flex items-end">
                    <button id="seed_run" class="rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-400">🌱 Générer</button>
                </div>
            </div>

            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between text-xs text-slate-400">
                    <div>Progression</div>
                    <div id="progress_label">—</div>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-950 ring-1 ring-slate-800">
                    <div id="progress_bar" class="h-3 w-0 bg-emerald-500"></div>
                </div>
                <div id="seed_status" class="mt-3 text-sm text-slate-300"></div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const btn = document.getElementById('seed_run');
        const statusEl = document.getElementById('seed_status');
        const barEl = document.getElementById('progress_bar');
        const labelEl = document.getElementById('progress_label');
        const countsGrid = document.getElementById('counts_grid');

        let source = null;

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
            if (!res.ok) throw new Error(data?.message || 'Request failed');
            return data;
        }

        function updateCounts(counts) {
            if (!counts || !countsGrid) return;
            Object.entries(counts).forEach(([k, v]) => {
                const el = countsGrid.querySelector(`[data-count="${k}"]`);
                if (el) el.textContent = new Intl.NumberFormat().format(v ?? 0);
            });
        }

        function setProgress(done, total) {
            const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
            barEl.style.width = pct + '%';
            labelEl.textContent = `${done}/${total} (${pct}%)`;
        }

        function startStream(job) {
            if (source) source.close();

            source = new EventSource(`{{ route('seed.stream') }}?job=${encodeURIComponent(job)}`);

            source.addEventListener('progress', (e) => {
                const data = JSON.parse(e.data || '{}');
                updateCounts(data.counts);

                const st = data.status;
                if (!st) {
                    statusEl.textContent = 'Aucun status en cache.';
                    return;
                }

                const done = st.article_created ?? 0;
                const total = st.article_target ?? 0;
                setProgress(done, total);
                statusEl.textContent = st.message || '';

                if (st.running === false) {
                    source.close();
                    source = null;
                }
            });

            source.addEventListener('server_error', (e) => {
                const data = JSON.parse(e.data || '{}');
                statusEl.textContent = data.message || 'Erreur';
                source.close();
                source = null;
            });
        }

        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.classList.add('opacity-60');

            const count = parseInt(document.getElementById('seed_count').value || '1000', 10);
            const fresh = document.getElementById('seed_fresh').checked;
            const authors = document.getElementById('seed_authors').value;
            const categories = document.getElementById('seed_categories').value;
            const tags = document.getElementById('seed_tags').value;
            const comments_per_article = parseFloat(document.getElementById('seed_comments').value || '3');

            try {
                const payload = await postJson('{{ route('seed.run') }}', {
                    count,
                    fresh,
                    authors: authors ? parseInt(authors, 10) : null,
                    categories: categories ? parseInt(categories, 10) : null,
                    tags: tags ? parseInt(tags, 10) : null,
                    comments_per_article,
                });
                statusEl.textContent = 'Seed démarré...';
                startStream(payload.job);
            } catch (e) {
                statusEl.textContent = e.message || 'Erreur';
            } finally {
                setTimeout(() => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60');
                }, 1500);
            }
        });

        // Auto-follow last job (if any)
        fetch('{{ route('seed.status') }}', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => {
                updateCounts(d.counts);
                if (d.job) startStream(d.job);
            })
            .catch(() => {});
    </script>
</x-layouts.app>
