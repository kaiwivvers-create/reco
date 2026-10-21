@php
    $winner = $explanation['winner'] ?? null;
    $comparison = $explanation['comparison'] ?? [];
    $runner = $results->firstWhere('rank', 2);
    $logo = app_logo_url();
    $totalWeight = collect($criteria)->sum('weight');
@endphp

<div class="report-document bg-white text-slate-800">
    <header class="flex items-start justify-between gap-6 border-b-2 pb-4" style="border-color: {{ brand_color() }}">
        <div class="flex items-center gap-3">
            @if ($logo)
                <img src="{{ $logo }}" alt="Logo" class="h-12 w-12 rounded-lg object-contain">
            @else
                <span class="flex h-12 w-12 items-center justify-center rounded-lg text-sm font-bold text-white" style="background: {{ brand_color() }}">
                    {{ app_initials(setting('app_name', 'SuppliRank')) }}
                </span>
            @endif
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-900">{{ setting('app_name', 'SuppliRank') }}</p>
                <p class="text-[11px] text-slate-500">{{ $company }}</p>
            </div>
        </div>
        <div class="text-right">
            <h1 class="text-base font-semibold text-slate-900">Laporan Evaluasi &amp; Rekomendasi Supplier</h1>
            <p class="mt-0.5 text-[11px] text-slate-500">{{ $evaluation->name }}</p>
            <p class="text-[10px] text-slate-400">Dicetak {{ fmt_date(now()) }}</p>
        </div>
    </header>

    <div class="mt-5 grid grid-cols-2 gap-4 text-[11px] sm:grid-cols-4">
        <div>
            <p class="text-[9px] uppercase tracking-wide text-slate-400">Metode</p>
            <p class="font-semibold text-slate-800">{{ strtoupper($evaluation->method) }}</p>
        </div>
        <div>
            <p class="text-[9px] uppercase tracking-wide text-slate-400">Tanggal evaluasi</p>
            <p class="font-semibold text-slate-800">{{ fmt_date($evaluation->created_at, false) }}</p>
        </div>
        <div>
            <p class="text-[9px] uppercase tracking-wide text-slate-400">Dibuat oleh</p>
            <p class="font-semibold text-slate-800">{{ $evaluation->creator?->name ?? 'Sistem' }}</p>
        </div>
        <div>
            <p class="text-[9px] uppercase tracking-wide text-slate-400">Peserta</p>
            <p class="font-semibold text-slate-800">{{ $results->count() }} supplier · {{ count($criteria) }} kriteria</p>
        </div>
    </div>

    @if ($winner)
        <section class="mt-6 rounded-xl border p-4" style="border-color: color-mix(in srgb, {{ brand_color() }} 30%, white); background: color-mix(in srgb, {{ brand_color() }} 4%, white)">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-[9px] font-semibold uppercase tracking-wide" style="color: {{ brand_color() }}">Rekomendasi utama</p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $winner['name'] }}</h2>
                    <p class="mt-1 max-w-2xl text-[11px] leading-relaxed text-slate-600">{{ $winner['summary'] }}</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-semibold text-slate-900">{{ fmt_num($winner['score']) }}</p>
                    <p class="text-[9px] uppercase tracking-wide text-slate-400">skor / 100</p>
                    @if ($runner)
                        <p class="mt-1 text-[10px] text-slate-500">+{{ fmt_num($comparison['margin'] ?? 0) }} dari #2</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="mt-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">1. Alasan rekomendasi</h2>
            <ol class="mt-2 space-y-1.5">
                @foreach (($winner['reasons'] ?? []) as $index => $reason)
                    <li class="flex gap-2 text-[11px] leading-relaxed text-slate-600">
                        <span class="font-semibold text-slate-800">{{ $index + 1 }}.</span>
                        <span>{{ $reason }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="mt-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">2. Peringkat supplier</h2>
            <table class="mt-2 w-full border-collapse text-[10px]">
                <thead>
                    <tr class="border-b border-slate-300 text-left text-slate-500">
                        <th class="py-1.5 pr-2">Peringkat</th>
                        <th class="py-1.5 pr-2">Supplier</th>
                        <th class="py-1.5 pr-2 text-right">Skor</th>
                        <th class="py-1.5 pr-2 text-right">Selisih</th>
                        <th class="py-1.5">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-2">#{{ $result->rank }}</td>
                            <td class="py-1.5 pr-2 font-medium text-slate-800">{{ $result->supplier_name }}</td>
                            <td class="py-1.5 pr-2 text-right">{{ fmt_num($result->score) }}</td>
                            <td class="py-1.5 pr-2 text-right">{{ $result->rank === 1 ? '—' : fmt_num($winner['score'] - $result->score) }}</td>
                            <td class="py-1.5">{{ $result->rank === 1 ? 'Direkomendasikan' : 'Alternatif' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @if ($runner && ! empty($comparison['rows']))
            <section class="mt-6">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">3. Perbandingan pemenang vs peringkat kedua</h2>
                <table class="mt-2 w-full border-collapse text-[10px]">
                    <thead>
                        <tr class="border-b border-slate-300 text-left text-slate-500">
                            <th class="py-1.5 pr-2">Kriteria</th>
                            <th class="py-1.5 pr-2">Bobot</th>
                            <th class="py-1.5 pr-2 text-right">{{ $winner['name'] }}</th>
                            <th class="py-1.5 pr-2 text-right">{{ $runner->supplier_name }}</th>
                            <th class="py-1.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison['rows'] as $row)
                            <tr class="border-b border-slate-100">
                                <td class="py-1.5 pr-2">{{ $row['name'] }} <span class="text-slate-400">({{ $row['type'] }})</span></td>
                                <td class="py-1.5 pr-2">{{ fmt_num($totalWeight > 0 ? (data_get(collect($criteria)->firstWhere('id', $row['criterion_id']), 'weight', 0) / $totalWeight) * 100 : 0, 1) }}%</td>
                                <td class="py-1.5 pr-2 text-right">{{ $row['winner_text'] }}</td>
                                <td class="py-1.5 pr-2 text-right">{{ $row['runner_text'] }}</td>
                                <td class="py-1.5 text-center font-medium capitalize">{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <section class="mt-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">4. Kontribusi kriteria pemenang</h2>
            <table class="mt-2 w-full border-collapse text-[10px]">
                <thead>
                    <tr class="border-b border-slate-300 text-left text-slate-500">
                        <th class="py-1.5 pr-2">Kriteria</th>
                        <th class="py-1.5 pr-2">Nilai</th>
                        <th class="py-1.5 pr-2 text-right">Bobot</th>
                        <th class="py-1.5 pr-2 text-right">Kontribusi (poin)</th>
                        <th class="py-1.5 text-right">Porsi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (($winner['contributions'] ?? []) as $contribution)
                        <tr class="border-b border-slate-100">
                            <td class="py-1.5 pr-2">{{ $contribution['name'] }}</td>
                            <td class="py-1.5 pr-2">{{ $contribution['raw_text'] }}</td>
                            <td class="py-1.5 pr-2 text-right">{{ fmt_num($contribution['weight_pct'], 1) }}%</td>
                            <td class="py-1.5 pr-2 text-right">{{ fmt_num($contribution['points'] ?? $contribution['contribution'] * 100) }}</td>
                            <td class="py-1.5 text-right">{{ fmt_num($contribution['share_pct'], 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="mt-6 space-y-2">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">5. Analisis Explainable AI</h2>
            @if ($comparison['counterfactual']['text'] ?? null)
                <p class="text-[10px] leading-relaxed text-slate-600"><span class="font-semibold text-slate-800">Kontrafaktual.</span> {{ $comparison['counterfactual']['text'] }}</p>
            @endif
            @if ($comparison['sensitivity']['text'] ?? null)
                <p class="text-[10px] leading-relaxed text-slate-600"><span class="font-semibold text-slate-800">Sensitivitas bobot.</span> {{ $comparison['sensitivity']['text'] }}</p>
            @endif
            <p class="text-[10px] leading-relaxed text-slate-500">{{ $explanation['method_note'] ?? '' }}</p>
        </section>

        <section class="mt-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">6. Penjelasan per supplier</h2>
            <div class="mt-2 space-y-3">
                @foreach ($results as $result)
                    @php($xai = $explanation['suppliers'][$result->supplier_id] ?? null)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold text-slate-800">#{{ $result->rank }} {{ $result->supplier_name }}</p>
                            <p class="text-[10px] text-slate-500">Skor {{ fmt_num($result->score) }}</p>
                        </div>
                        <p class="mt-1 text-[10px] leading-relaxed text-slate-600">{{ $xai['narrative'] ?? 'Tidak ada narasi.' }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <footer class="mt-6 border-t border-slate-200 pt-3">
            <p class="text-[9px] leading-relaxed text-slate-400">
                {{ setting('report_footer') }}
                Rekomendasi dihasilkan dari bobot dan data yang dimasukkan pengguna; sistem tidak mengasumsikan
                supplier dengan harga terendah sebagai pilihan terbaik.
            </p>
        </footer>
    @else
        <p class="mt-8 text-center text-xs text-slate-500">Evaluasi ini belum memiliki hasil untuk dilaporkan.</p>
    @endif
</div>
