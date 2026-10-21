@extends('layouts.app')

@section('title', 'Laporan')
@section('heading', 'Laporan Evaluasi')
@section('subheading', 'Unduh atau cetak laporan rekomendasi supplier')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 gap-2">
            <div class="relative flex-1 sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari laporan…" class="input pl-9">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
        </form>
        <p class="text-[11px] text-slate-500">{{ $evaluations->total() }} laporan tersedia</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($evaluations as $evaluation)
            @php($winner = $evaluation->results->firstWhere('rank', 1))
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="badge badge-brand">{{ strtoupper($evaluation->method) }}</span>
                            <span class="badge badge-slate">{{ $evaluation->results->count() }} supplier</span>
                        </div>
                        <h3 class="mt-2 truncate text-sm font-semibold text-slate-900">{{ $evaluation->name }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">
                            {{ fmt_date($evaluation->created_at, false) }} · {{ $evaluation->creator?->name ?? 'Sistem' }}
                        </p>
                    </div>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <x-icon name="file" class="h-4 w-4" />
                    </span>
                </div>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="text-[9px] uppercase tracking-wide text-slate-400">Rekomendasi</p>
                        <p class="truncate text-xs font-medium text-slate-800">{{ $winner?->supplier_name ?? 'Belum ada hasil' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[9px] uppercase tracking-wide text-slate-400">Skor</p>
                        <p class="text-xs font-semibold text-slate-800">{{ $winner ? fmt_num($winner->score) : '—' }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('reports.show', $evaluation) }}" class="btn btn-secondary btn-sm">
                        <x-icon name="eye" class="h-3.5 w-3.5" /> Lihat
                    </a>
                    <a href="{{ route('reports.pdf', $evaluation) }}" class="btn btn-secondary btn-sm">
                        <x-icon name="download" class="h-3.5 w-3.5" /> PDF
                    </a>
                    <a href="{{ route('reports.excel', $evaluation) }}" class="btn btn-secondary btn-sm">
                        <x-icon name="table" class="h-3.5 w-3.5" /> Excel
                    </a>
                    <a href="{{ route('reports.print', $evaluation) }}" target="_blank" class="btn btn-secondary btn-sm">
                        <x-icon name="print" class="h-3.5 w-3.5" /> Print
                    </a>
                </div>
            </div>
        @empty
            <div class="card flex flex-col items-center justify-center px-6 py-14 text-center lg:col-span-2">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                    <x-icon name="file" class="h-5 w-5" />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-700">Belum ada laporan</p>
                <p class="mt-1 text-xs text-slate-500">Laporan dibuat dari evaluasi supplier yang sudah dijalankan.</p>
                @if (can_manage('evaluations'))
                    <a href="{{ route('evaluations.create') }}" class="btn btn-primary btn-sm mt-4">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Mulai evaluasi
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    @if ($evaluations->hasPages())
        <div class="mt-4">{{ $evaluations->links() }}</div>
    @endif
@endsection
