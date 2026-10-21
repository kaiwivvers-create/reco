@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subheading', 'Ringkasan data supplier, evaluasi, dan rekomendasi terbaru')

@section('actions')
    @if (can_manage('evaluations'))
        <a href="{{ route('evaluations.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Evaluasi baru
        </a>
    @endif
@endsection

@section('content')
    @php
        $winner = $stats['winner'];
        $winnerXai = $latestEvaluation?->explanation['winner'] ?? null;
        $maxScore = $latestEvaluation ? max(1, $latestEvaluation->results->max('score')) : 1;
        $activityColors = [
            'emerald' => 'green', 'amber' => 'amber', 'rose' => 'red', 'sky' => 'blue',
            'slate' => 'slate', 'violet' => 'violet', 'indigo' => 'blue', 'cyan' => 'cyan',
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Total supplier</p>
                    <p class="mt-1.5 text-2xl font-semibold text-slate-900">{{ $stats['suppliers'] }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ $stats['active_suppliers'] }} supplier aktif</p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <x-icon name="building" class="h-4.5 w-4.5" />
                </span>
            </div>
            <a href="{{ route('suppliers.index') }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-medium text-[var(--brand)] hover:underline">
                Kelola data supplier <x-icon name="arrow-right" class="h-3 w-3" />
            </a>
        </div>

        <div class="card card-pad">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Evaluasi tersimpan</p>
                    <p class="mt-1.5 text-2xl font-semibold text-slate-900">{{ $stats['evaluations'] }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ $latestEvaluation ? 'Terakhir '.fmt_date($stats['last_evaluation'], false) : 'Belum ada evaluasi' }}</p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <x-icon name="chart" class="h-4.5 w-4.5" />
                </span>
            </div>
            <a href="{{ route('evaluations.index') }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-medium text-[var(--brand)] hover:underline">
                Lihat hasil evaluasi <x-icon name="arrow-right" class="h-3 w-3" />
            </a>
        </div>

        <div class="card card-pad">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Kriteria aktif</p>
                    <p class="mt-1.5 text-2xl font-semibold text-slate-900">{{ $stats['criteria'] }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">Total bobot {{ fmt_num($totalWeight, 1) }}%</p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <x-icon name="sliders" class="h-4.5 w-4.5" />
                </span>
            </div>
            <a href="{{ route('criteria.index') }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-medium text-[var(--brand)] hover:underline">
                Atur bobot kriteria <x-icon name="arrow-right" class="h-3 w-3" />
            </a>
        </div>

        <div class="card card-pad">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Rekomendasi terakhir</p>
                    <p class="mt-1.5 truncate text-lg font-semibold text-slate-900">{{ $winner?->supplier_name ?? '—' }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        @if ($winner)
                            Skor {{ fmt_num($winner->score) }} · unggul {{ fmt_num($stats['winner_margin'] ?? 0) }} poin
                        @else
                            Jalankan evaluasi pertama
                        @endif
                    </p>
                </div>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg text-white" style="background: {{ brand_color() }}">
                    <x-icon name="sparkles" class="h-4.5 w-4.5" />
                </span>
            </div>
            @if ($latestEvaluation)
                <a href="{{ route('evaluations.show', $latestEvaluation) }}" class="mt-3 inline-flex items-center gap-1 text-[11px] font-medium text-[var(--brand)] hover:underline">
                    Buka penjelasan <x-icon name="arrow-right" class="h-3 w-3" />
                </a>
            @else
                <span class="mt-3 inline-flex items-center gap-1 text-[11px] text-slate-400">Belum ada data</span>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            @if ($latestEvaluation && $winner)
                <div class="card overflow-hidden">
                    <div class="relative p-6 text-white" style="background: linear-gradient(120deg, var(--brand) 0%, color-mix(in srgb, var(--brand) 55%, #0f172a) 100%)">
                        <div class="absolute -right-10 -top-16 h-48 w-48 rounded-full bg-white/10"></div>
                        <div class="relative flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="badge bg-white/15 text-white">Rekomendasi #1</span>
                                    <span class="badge bg-white/15 text-white">{{ strtoupper($latestEvaluation->method) }}</span>
                                </div>
                                <h2 class="mt-3 text-2xl font-semibold">{{ $winner->supplier_name }}</h2>
                                <p class="mt-1 text-xs text-white/70">{{ $latestEvaluation->name }} · {{ fmt_date($latestEvaluation->created_at, false) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-3xl font-semibold">{{ fmt_num($winner->score) }}</p>
                                <p class="text-[10px] uppercase tracking-wide text-white/60">dari 100</p>
                            </div>
                        </div>

                        @if ($winnerXai)
                            <p class="relative mt-4 max-w-2xl text-[13px] leading-relaxed text-white/85">{{ $winnerXai['summary'] }}</p>
                            <div class="relative mt-4 flex flex-wrap gap-2">
                                @foreach (array_slice($winnerXai['strengths'], 0, 3) as $strength)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] text-white/90">
                                        <x-icon name="check" class="h-3 w-3" /> {{ $strength['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="relative mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('evaluations.show', $latestEvaluation) }}" class="btn btn-sm bg-white text-slate-900 hover:bg-white/90">
                                <x-icon name="sparkles" class="h-3.5 w-3.5" /> Lihat alasan rekomendasi
                            </a>
                            @if (can_view('reports'))
                                <a href="{{ route('reports.show', $latestEvaluation) }}" class="btn btn-sm border border-white/25 bg-white/10 text-white hover:bg-white/20">
                                    <x-icon name="file" class="h-3.5 w-3.5" /> Laporan
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="card-pad">
                        <div class="flex items-center justify-between">
                            <h3 class="section-title">Perbandingan skor</h3>
                            <span class="muted">Skala 0–100</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach ($latestEvaluation->results as $result)
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-xs">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-[10px] font-semibold {{ $result->rank === 1 ? 'text-white' : 'bg-slate-100 text-slate-500' }}"
                                                  @if ($result->rank === 1) style="background: {{ brand_color() }}" @endif
                                            >{{ $result->rank }}</span>
                                            <span class="truncate font-medium text-slate-700">{{ $result->supplier_name }}</span>
                                        </div>
                                        <span class="font-semibold tabular-nums text-slate-800">{{ fmt_num($result->score) }}</span>
                                    </div>
                                    <div class="meter mt-1.5">
                                        <div class="meter-fill" style="width: {{ max(3, ($result->score / $maxScore) * 100) }}%; {{ $result->rank === 1 ? '' : 'background:#94a3b8' }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="card flex flex-col items-center justify-center px-6 py-14 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-[var(--brand)]">
                        <x-icon name="chart" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">Belum ada evaluasi supplier</h3>
                    <p class="mt-1.5 max-w-md text-xs leading-relaxed text-slate-500">
                        Masukkan minimal dua supplier dengan nilai kriteria, lalu jalankan evaluasi untuk melihat
                        peringkat dan penjelasan rekomendasi.
                    </p>
                    @if (can_manage('evaluations'))
                        <a href="{{ route('evaluations.create') }}" class="btn btn-primary mt-5">
                            <x-icon name="plus" class="h-4 w-4" /> Mulai evaluasi
                        </a>
                    @endif
                </div>
            @endif

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="section-title">Aktivitas terbaru</h3>
                        <p class="muted">Perubahan data yang tercatat sistem</p>
                    </div>
                    @if (can_view('activity'))
                        <a href="{{ route('activity.index') }}" class="text-[11px] font-medium text-[var(--brand)] hover:underline">Lihat semua</a>
                    @endif
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recentActivities as $activity)
                        @php($color = $activityColors[\App\Models\ActivityLog::ACTION_COLORS[$activity->action] ?? 'slate'] ?? 'slate')
                        <div class="flex items-start gap-3 px-5 py-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                                <x-icon name="{{ $activity->action === 'deleted' ? 'trash' : ($activity->action === 'reverted' ? 'restore' : 'edit') }}" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium text-slate-800">{{ $activity->label ?? $activity->modelLabel() }}</span>
                                    <span class="badge badge-{{ $color }}">{{ $activity->actionLabel() }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $activity->description }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] text-slate-400">{{ $activity->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-400">Belum ada aktivitas tercatat.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="section-title">Bobot kriteria</h3>
                    <a href="{{ route('criteria.index') }}" class="text-[11px] font-medium text-[var(--brand)] hover:underline">Atur</a>
                </div>
                <div class="space-y-4 px-5 py-4">
                    @forelse ($criteria as $criterion)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <span class="truncate font-medium text-slate-700">{{ $criterion->name }}</span>
                                    <span class="badge {{ $criterion->isCost() ? 'badge-amber' : 'badge-green' }}">{{ $criterion->isCost() ? 'cost' : 'benefit' }}</span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-slate-700">
                                    {{ fmt_num($totalWeight > 0 ? ($criterion->weight / $totalWeight) * 100 : 0, 1) }}%
                                </span>
                            </div>
                            <div class="meter mt-1.5">
                                <div class="meter-fill" style="width: {{ $totalWeight > 0 ? ($criterion->weight / $totalWeight) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-slate-400">Belum ada kriteria aktif.</p>
                    @endforelse
                </div>
                <div class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500">
                    Normalisasi bobot dilakukan otomatis saat evaluasi.
                </div>
            </div>

            <div class="card">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="section-title">Kelengkapan data nilai</h3>
                    <p class="muted">Jumlah supplier yang memiliki nilai per kriteria</p>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @forelse ($coverage as $row)
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-xs text-slate-600">{{ $row['name'] }}</span>
                            <span class="badge {{ $row['scored'] === $row['total'] && $row['total'] > 0 ? 'badge-green' : 'badge-amber' }}">
                                {{ $row['scored'] }}/{{ $row['total'] }}
                            </span>
                        </div>
                    @empty
                        <p class="py-2 text-center text-xs text-slate-400">Belum ada kriteria.</p>
                    @endforelse
                </div>
            </div>

            <div class="card card-pad">
                <h3 class="section-title">Metode evaluasi</h3>
                <div class="mt-3 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-600">SAW</span>
                        <span class="font-semibold text-slate-800">{{ $methodSplit['saw'] ?? 0 }} evaluasi</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-600">TOPSIS</span>
                        <span class="font-semibold text-slate-800">{{ $methodSplit['topsis'] ?? 0 }} evaluasi</span>
                    </div>
                </div>
                <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                    <span class="font-medium text-slate-700">Catatan.</span>
                    Sistem tidak mengasumsikan supplier termurah adalah yang terbaik. Semua kriteria dihitung
                    sesuai bobot dan arah (benefit/cost).
                </div>
            </div>
        </div>
    </div>
@endsection
