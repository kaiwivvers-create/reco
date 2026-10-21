@extends('layouts.app')

@section('title', $evaluation->name)
@section('heading', 'Hasil Evaluasi')
@section('subheading', $evaluation->name)

@section('actions')
    @if (can_view('reports'))
        <a href="{{ route('reports.show', $evaluation) }}" class="btn btn-secondary btn-sm">
            <x-icon name="file" class="h-3.5 w-3.5" /> <span class="hidden sm:inline">Laporan</span>
        </a>
        <a href="{{ route('reports.pdf', $evaluation) }}" class="btn btn-secondary btn-sm">
            <x-icon name="download" class="h-3.5 w-3.5" /> <span class="hidden sm:inline">PDF</span>
        </a>
    @endif
@endsection

@section('content')
    @php
        $results = $evaluation->results;
        $winner = $explanation['winner'] ?? null;
        $runner = $results->firstWhere('rank', 2);
        $winnerResult = $results->firstWhere('rank', 1);
        $criteria = $evaluation->criteria_snapshot ?? [];
        $totalWeight = collect($criteria)->sum('weight');
        $comparison = $explanation['comparison'] ?? [];
        $supplierXai = $explanation['suppliers'] ?? [];
        $maxScore = max(1, (float) $results->max('score'));
        $statusBadge = ['unggul' => 'badge-green', 'tertinggal' => 'badge-red', 'setara' => 'badge-slate'];
        $gap = fn ($score) => $winnerResult ? $winnerResult->score - $score : 0;
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
        <span class="badge badge-brand">{{ strtoupper($evaluation->method) }}</span>
        <span>{{ fmt_date($evaluation->created_at) }}</span>
        <span>·</span>
        <span>oleh {{ $evaluation->creator?->name ?? 'Sistem' }}</span>
        <span>·</span>
        <span>{{ $results->count() }} supplier</span>
        <span>·</span>
        <span>{{ count($criteria) }} kriteria</span>
        @if (can_manage('evaluations'))
            <form method="POST" action="{{ route('evaluations.destroy', $evaluation) }}" class="ml-auto" data-confirm="Hapus evaluasi ini?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger-soft btn-xs">
                    <x-icon name="trash" class="h-3 w-3" /> Hapus
                </button>
            </form>
        @endif
    </div>

    @if (! $winner)
        <div class="card p-8 text-center text-xs text-slate-500">
            Data penjelasan tidak tersedia untuk evaluasi ini. Jalankan ulang evaluasi untuk mendapatkan analisis terbaru.
            <a href="{{ route('evaluations.create') }}" class="ml-1 font-semibold text-[var(--brand)] underline">Evaluasi baru</a>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="relative p-6 text-white" style="background: linear-gradient(125deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 45%, #0f172a) 100%)">
                <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10"></div>
                <div class="relative grid gap-6 lg:grid-cols-[1.4fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge bg-white/15 text-white">Supplier terbaik</span>
                            <span class="badge bg-white/15 text-white">{{ $explanation['method_label'] ?? strtoupper($evaluation->method) }}</span>
                            @if ($runner)
                                <span class="badge bg-white/15 text-white">unggul {{ fmt_num($comparison['margin'] ?? 0) }} poin</span>
                            @endif
                        </div>
                        <h2 class="mt-3 text-2xl font-semibold sm:text-3xl">{{ $winner['name'] }}</h2>
                        <p class="mt-3 max-w-2xl text-[13px] leading-relaxed text-white/85">{{ $winner['summary'] }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (array_slice($winner['strengths'] ?? [], 0, 3) as $strength)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] text-white/90">
                                    <x-icon name="check" class="h-3 w-3" /> {{ $strength['name'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex items-center gap-5 lg:flex-col lg:items-end lg:gap-2">
                        <div class="text-left lg:text-right">
                            <p class="text-4xl font-semibold">{{ fmt_num($winner['score']) }}</p>
                            <p class="text-[10px] uppercase tracking-wide text-white/60">skor akhir / 100</p>
                        </div>
                        @if ($runner)
                            <div class="rounded-lg bg-white/10 px-3 py-2 text-[10px] text-white/80">
                                <p class="text-white/60">Selisih dengan peringkat 2 ({{ $runner->supplier_name }})</p>
                                <p class="mt-0.5 text-sm font-semibold text-white">{{ fmt_num($comparison['margin'] ?? 0) }} poin</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-3">
            <div class="space-y-5 xl:col-span-2">
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="section-title">Peringkat supplier</h3>
                            <p class="muted">Skor 0–100 berdasarkan {{ strtoupper($evaluation->method) }}</p>
                        </div>
                        <span class="text-[10px] text-slate-400">{{ $results->count() }} peserta</span>
                    </div>
                    <div class="space-y-4 p-5">
                        @foreach ($results as $result)
                            <div class="rounded-xl border px-4 py-3 {{ $result->rank === 1 ? 'border-[var(--brand)]' : 'border-slate-200' }}"
                                 @if ($result->rank === 1) style="background: color-mix(in srgb, var(--brand) 4%, white)" @endif>
                                <div class="flex flex-wrap items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold {{ $result->rank === 1 ? 'text-white' : 'bg-slate-100 text-slate-500' }}"
                                          @if ($result->rank === 1) style="background: {{ brand_color() }}" @endif>
                                        {{ $result->rank }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ $result->supplier ? route('suppliers.show', $result->supplier) : '#' }}" class="truncate text-sm font-semibold text-slate-800 hover:text-[var(--brand)]">
                                                {{ $result->supplier_name }}
                                            </a>
                                            @if ($result->rank === 1)
                                                <span class="badge badge-brand">direkomendasikan</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-slate-400">
                                            <span>{{ match ($result->rank) { 1 => 'Peringkat pertama', 2 => 'Peringkat kedua', 3 => 'Peringkat ketiga', default => 'Peringkat '.$result->rank } }}</span>
                                            @if ($result->rank > 1)
                                                <span>−{{ fmt_num($gap($result->score)) }} poin dari pemenang</span>
                                            @endif
                                            @if ($evaluation->method === 'topsis' && $result->detail['d_plus'] !== null)
                                                <span>D+ {{ fmt_num($result->detail['d_plus'], 3) }} · D− {{ fmt_num($result->detail['d_minus'], 3) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-lg font-semibold tabular-nums text-slate-900">{{ fmt_num($result->score) }}</p>
                                        <p class="text-[9px] uppercase tracking-wide text-slate-400">skor</p>
                                    </div>
                                </div>
                                <div class="meter mt-3">
                                    <div class="meter-fill" style="width: {{ max(2, ($result->score / $maxScore) * 100) }}%; {{ $result->rank === 1 ? '' : 'background:#94a3b8' }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <x-icon name="sparkles" class="h-4 w-4 text-[var(--brand)]" />
                            <h3 class="section-title">Mengapa {{ $winner['name'] }}?</h3>
                        </div>
                        <p class="muted">Alasan yang diturunkan langsung dari matriks keputusan</p>
                    </div>
                    <div class="p-5">
                        <ol class="space-y-3">
                            @foreach (($winner['reasons'] ?? []) as $index => $reason)
                                <li class="flex gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[10px] font-semibold text-indigo-600">{{ $index + 1 }}</span>
                                    <p class="text-[12px] leading-relaxed text-slate-600">{{ $reason }}</p>
                                </li>
                            @endforeach
                        </ol>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                                <p class="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-800">
                                    <x-icon name="arrow-up" class="h-3.5 w-3.5" /> Kekuatan utama
                                </p>
                                <ul class="mt-2 space-y-2">
                                    @forelse ($winner['strengths'] ?? [] as $strength)
                                        <li class="text-[11px] leading-relaxed text-emerald-900/80">{{ $strength['text'] }}</li>
                                    @empty
                                        <li class="text-[11px] text-emerald-700">—</li>
                                    @endforelse
                                </ul>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                                <p class="flex items-center gap-1.5 text-[11px] font-semibold text-amber-800">
                                    <x-icon name="arrow-down" class="h-3.5 w-3.5" /> Catatan perbaikan
                                </p>
                                <ul class="mt-2 space-y-2">
                                    @forelse ($winner['weaknesses'] ?? [] as $weakness)
                                        <li class="text-[11px] leading-relaxed text-amber-900/80">{{ $weakness['text'] }}</li>
                                    @empty
                                        <li class="text-[11px] text-amber-700">Tidak ada kelemahan berarti pada kriteria yang dinilai.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="section-title">Kontribusi kriteria — {{ $winner['name'] }}</h3>
                        <p class="muted">Bobot × performa ternormalisasi. Total kontribusi membentuk skor akhir.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kriteria</th>
                                    <th>Tipe</th>
                                    <th class="text-right">Nilai</th>
                                    <th class="text-right">Bobot</th>
                                    <th>Kontribusi</th>
                                    <th class="text-right">Porsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (($winner['contributions'] ?? []) as $contribution)
                                    <tr>
                                        <td class="font-medium text-slate-800">{{ $contribution['name'] }}</td>
                                        <td><span class="badge {{ $contribution['type'] === 'cost' ? 'badge-amber' : 'badge-green' }}">{{ $contribution['type'] }}</span></td>
                                        <td class="text-right text-xs">{{ $contribution['raw_text'] }}</td>
                                        <td class="text-right text-xs">{{ fmt_num($contribution['weight_pct'], 1) }}%</td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="meter w-24">
                                                    <div class="meter-fill" style="width: {{ min(100, $contribution['share_pct']) }}%"></div>
                                                </div>
                                                <span class="text-[10px] tabular-nums text-slate-500">{{ fmt_num($contribution['points'] ?? $contribution['contribution'] * 100) }} poin</span>
                                            </div>
                                        </td>
                                        <td class="text-right text-xs font-medium">{{ fmt_num($contribution['share_pct'], 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 px-5 py-3 text-[10px] leading-relaxed text-slate-400">
                        {{ $explanation['method_note'] ?? '' }}
                    </div>
                </div>

                @if ($runner && ! empty($comparison['rows']))
                    <div class="card">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                            <div>
                                <h3 class="section-title">Perbandingan head-to-head</h3>
                                <p class="muted">{{ $winner['name'] }} vs {{ $runner->supplier_name }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span class="badge badge-green">{{ count($comparison['won'] ?? []) }} unggul</span>
                                <span class="badge badge-red">{{ count($comparison['lost'] ?? []) }} tertinggal</span>
                                <span class="badge badge-slate">{{ count($comparison['tied'] ?? []) }} setara</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Kriteria</th>
                                        <th class="text-right">{{ $winner['name'] }}</th>
                                        <th class="text-right">{{ $runner->supplier_name }}</th>
                                        <th>Performa</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($comparison['rows'] as $row)
                                        <tr>
                                            <td>
                                                <span class="text-xs font-medium text-slate-800">{{ $row['name'] }}</span>
                                                <span class="ml-1 text-[10px] text-slate-400">{{ $row['type'] }}</span>
                                            </td>
                                            <td class="text-right text-xs font-medium">{{ $row['winner_text'] }}</td>
                                            <td class="text-right text-xs">{{ $row['runner_text'] }}</td>
                                            <td>
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="meter h-1.5 w-16"><div class="meter-fill" style="width: {{ $row['winner_performance'] }}%"></div></div>
                                                        <span class="text-[9px] text-slate-400">{{ fmt_num($row['winner_performance'], 0) }}%</span>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="meter h-1.5 w-16"><div class="meter-fill" style="width: {{ $row['runner_performance'] }}%; background:#94a3b8"></div></div>
                                                        <span class="text-[9px] text-slate-400">{{ fmt_num($row['runner_performance'], 0) }}%</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $statusBadge[$row['status']] ?? 'badge-slate' }}">{{ $row['status'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="card card-pad">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icon name="refresh" class="h-3.5 w-3.5" /></span>
                            <h3 class="section-title">Analisis kontrafaktual</h3>
                        </div>
                        <p class="mt-3 text-[11px] leading-relaxed text-slate-600">
                            {{ $comparison['counterfactual']['text'] ?? 'Tidak tersedia.' }}
                        </p>
                        @if (! empty($comparison['counterfactual']['possible']))
                            <div class="mt-3 rounded-lg bg-violet-50 px-3 py-2 text-[10px] text-violet-800">
                                Perubahan {{ $comparison['counterfactual']['direction'] }} {{ $comparison['counterfactual']['criterion'] }}
                                sebesar {{ fmt_num($comparison['counterfactual']['change_pct'], 1) }}% sudah cukup menyamakan skor.
                            </div>
                        @endif
                    </div>

                    <div class="card card-pad">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><x-icon name="scale" class="h-3.5 w-3.5" /></span>
                            <h3 class="section-title">Sensitivitas bobot</h3>
                        </div>
                        <p class="mt-3 text-[11px] leading-relaxed text-slate-600">
                            {{ $comparison['sensitivity']['text'] ?? 'Tidak tersedia.' }}
                        </p>
                        @if (! empty($comparison['sensitivity']['scenarios']))
                            <div class="mt-3 flex items-center gap-2">
                                <div class="meter flex-1">
                                    <div class="meter-fill" style="width: {{ $comparison['sensitivity']['total'] > 0 ? ($comparison['sensitivity']['stable'] / $comparison['sensitivity']['total']) * 100 : 0 }}%"></div>
                                </div>
                                <span class="text-[10px] font-medium text-slate-500">
                                    {{ $comparison['sensitivity']['stable'] }}/{{ $comparison['sensitivity']['total'] }} stabil
                                </span>
                            </div>
                            <details class="mt-2">
                                <summary class="cursor-pointer text-[10px] font-medium text-[var(--brand)]">Lihat skenario</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($comparison['sensitivity']['scenarios'] as $scenario)
                                        <li class="flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                            <span>{{ $scenario['criterion'] }} {{ $scenario['percent'] > 0 ? '+' : '' }}{{ $scenario['percent'] }}%</span>
                                            <span class="font-medium {{ $scenario['stable'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                                {{ $scenario['winner'] }}{{ $scenario['stable'] ? ' ✓' : ' (berubah)' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="card card-pad">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-[var(--brand)]"><x-icon name="sparkles" class="h-3.5 w-3.5" /></span>
                        <h3 class="section-title">Narasi AI (opsional)</h3>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">
                        Ringkasan eksekutif dari data evaluasi ini. Penjelasan deterministik di samping tetap menjadi
                        acuan utama dan tidak bergantung pada layanan AI.
                    </p>
                    <button type="button" class="btn btn-secondary btn-sm mt-3 w-full"
                            data-ai-narrative="#ai-output-{{ $evaluation->id }}"
                            data-url="{{ route('evaluations.ai', $evaluation) }}">
                        <x-icon name="sparkles" class="h-3.5 w-3.5" /> Buat narasi AI
                    </button>
                    <div id="ai-output-{{ $evaluation->id }}" class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-600"></div>
                    <p class="mt-2 text-[10px] text-slate-400">
                        {{ setting('ai_enabled') === '1' ? 'Fitur AI aktif. Jika gagal, periksa API key di Pengaturan.' : 'Belum aktif. Atur API key di Pengaturan Aplikasi.' }}
                    </p>
                </div>

                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="section-title">Matriks keputusan</h3>
                        <p class="muted">Nilai mentah supplier per kriteria</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table text-[11px]">
                            <thead>
                                <tr>
                                    <th>Kriteria</th>
                                    @foreach ($results as $result)
                                        <th class="text-right">{{ \Illuminate\Support\Str::limit($result->supplier_name, 14) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($criteria as $criterion)
                                    <tr>
                                        <td>
                                            <span class="block text-[11px] font-medium text-slate-700">{{ $criterion['name'] }}</span>
                                            <span class="text-[9px] text-slate-400">
                                                {{ $criterion['type'] }} · {{ fmt_num($totalWeight > 0 ? ($criterion['weight'] / $totalWeight) * 100 : 0, 1) }}%
                                            </span>
                                        </td>
                                        @foreach ($results as $result)
                                            @php($raw = $result->detail['matrix'][$criterion['id']]['raw'] ?? null)
                                            <td class="text-right text-[11px]">{{ $raw === null ? '—' : fmt_num($raw) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr class="bg-slate-50">
                                    <td class="text-[11px] font-semibold text-slate-700">Skor akhir</td>
                                    @foreach ($results as $result)
                                        <td class="text-right text-[11px] font-semibold text-slate-800">{{ fmt_num($result->score) }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <details class="border-t border-slate-100">
                        <summary class="cursor-pointer px-5 py-3 text-[10px] font-medium text-[var(--brand)]">Lihat matriks ternormalisasi</summary>
                        <div class="overflow-x-auto px-2 pb-3">
                            <table class="table text-[10px]">
                                <thead>
                                    <tr>
                                        <th>Kriteria</th>
                                        @foreach ($results as $result)
                                            <th class="text-right">{{ \Illuminate\Support\Str::limit($result->supplier_name, 12) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($criteria as $criterion)
                                        <tr>
                                            <td>{{ $criterion['name'] }}</td>
                                            @foreach ($results as $result)
                                                <td class="text-right">{{ fmt_num($result->detail['matrix'][$criterion['id']]['normalized'] ?? 0, 4) }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>

                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="section-title">Penjelasan semua supplier</h3>
                        <p class="muted">Termasuk supplier yang tidak terpilih</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($results as $result)
                            @php($xai = $supplierXai[$result->supplier_id] ?? null)
                            <details class="group">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-5 py-3 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-slate-800">#{{ $result->rank }} {{ $result->supplier_name }}</p>
                                        <p class="text-[10px] text-slate-400">Skor {{ fmt_num($result->score) }}</p>
                                    </div>
                                    <x-icon name="chevron-down" class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
                                </summary>
                                <div class="px-5 pb-4">
                                    <p class="text-[11px] leading-relaxed text-slate-600">{{ $xai['narrative'] ?? 'Tidak ada narasi.' }}</p>
                                    <div class="mt-3 space-y-2">
                                        @foreach (array_slice($xai['contributions'] ?? [], 0, 4) as $contribution)
                                            <div>
                                                <div class="flex items-center justify-between text-[10px]">
                                                    <span class="text-slate-500">{{ $contribution['name'] }}</span>
                                                    <span class="font-medium text-slate-700">{{ fmt_num($contribution['share_pct'], 1) }}%</span>
                                                </div>
                                                <div class="meter mt-1 h-1"><div class="meter-fill" style="width: {{ min(100, $contribution['share_pct']) }}%"></div></div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-relaxed text-amber-800">
                    <p class="font-semibold">Rekomendasi bukan keputusan otomatis.</p>
                    <p class="mt-1 text-amber-700">
                        Sistem tidak memilih harga termurah secara otomatis. Bobot kriteria, kualitas data,
                        dan kebutuhan pengadaan tetap perlu dikonfirmasi oleh tim terkait.
                    </p>
                </div>
            </div>
        </div>
    @endif
@endsection
