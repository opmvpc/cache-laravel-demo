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
            <div class="mt-3 grid gap-2 text-sm text-slate-300 md:grid-cols-3">
                @foreach ($counts as $k => $v)
                    <div class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $k }}</div>
                        <div class="mt-1 font-mono text-lg">{{ number_format($v) }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="text-sm font-semibold text-slate-200">Générer des données</div>
            <div class="mt-4 flex flex-col gap-4 md:flex-row md:items-end">
                <label class="block">
                    <div class="mb-1 text-xs text-slate-400">Nombre d'articles</div>
                    <input id="seed_count" type="number" min="1" max="200000" value="1000" class="w-48 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" />
                </label>
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 px-4 py-3 text-sm">
                    <input id="seed_fresh" type="checkbox" />
                    <span>Vider les tables avant</span>
                </label>
                <button id="seed_run" class="rounded-lg bg-emerald-500 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-400">🌱 Générer</button>
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

        let pollTimer = null;

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

        async function getJson(url) {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            return await res.json().catch(() => ({}));
        }

        function setProgress(done, total) {
            const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
            barEl.style.width = pct + '%';
            labelEl.textContent = `${done}/${total} (${pct}%)`;
        }

        async function poll() {
            const data = await getJson('{{ route('seed.status') }}');
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
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.classList.add('opacity-60');

            const count = parseInt(document.getElementById('seed_count').value || '1000', 10);
            const fresh = document.getElementById('seed_fresh').checked;

            try {
                await postJson('{{ route('seed.run') }}', { count, fresh });
                statusEl.textContent = 'Seed démarré...';
                if (pollTimer) clearInterval(pollTimer);
                await poll();
                pollTimer = setInterval(poll, 2000);
            } catch (e) {
                statusEl.textContent = e.message || 'Erreur';
            } finally {
                setTimeout(() => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-60');
                }, 1500);
            }
        });

        poll();
    </script>
</x-layouts.app>

