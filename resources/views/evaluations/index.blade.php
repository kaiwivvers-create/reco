@extends('layouts.app')

@section('title', 'Evaluasi & Ranking')
@section('heading', 'Evaluasi & Ranking')
@section('subheading', 'Riwayat perhitungan MCDM beserta pemenang dan skornya')

@section('actions')
    @if (can_manage('evaluations'))
        <a href="{{ route('evaluations.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Evaluasi baru
        </a>
    @endif
@endsection

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row">
            <div class="relative flex-1 sm:max-w-xs">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama evaluasi…" class="input pl-9">
            </div>
            <select name="method" class="select sm:w-40" onchange="this.form.submit()">
                <option value="">Semua metode</option>
                <option value="saw" @selected(request('method') === 'saw')>SAW</option>
                <option value="topsis" @selected(request('method') === 'topsis')>TOPSIS</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
            @if (request()->filled('q') || request()->filled('method'))
                <a href="{{ route('evaluations.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
        <p class="text-[11px] text-slate-500">{{ $evaluations->total() }} evaluasi tersimpan</p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Evaluasi</th>
                        <th>Metode</th>
                        <th>Peserta</th>
                        <th>Rekomendasi</th>
                        <th>Skor</th>
                        <th>Tanggal</th>
                        <th class="w-32 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        @php($winner = $evaluation->results->firstWhere('rank', 1))
                        <tr>
                            <td>
                                <a href="{{ route('evaluations.show', $evaluation) }}" class="block max-w-xs truncate text-[13px] font-semibold text-slate-800 hover:text-[var(--brand)]">
                                    {{ $evaluation->name }}
                                </a>
                                <span class="text-[10px] text-slate-400">oleh {{ $evaluation->creator?->name ?? 'Sistem' }}</span>
                            </td>
                            <td><span class="badge badge-brand">{{ strtoupper($evaluation->method) }}</span></td>
                            <td><span class="text-xs text-slate-600">{{ $evaluation->results->count() }} supplier</span></td>
                            <td>
                                @if ($winner)
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-800">
                                        <x-icon name="sparkles" class="h-3.5 w-3.5 text-[var(--brand)]" />
                                        {{ $winner->supplier_name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-xs font-semibold tabular-nums text-slate-700">{{ $winner ? fmt_num($winner->score) : '—' }}</span>
                            </td>
                            <td><span class="text-[11px] text-slate-500">{{ fmt_date($evaluation->created_at, false) }}</span></td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('evaluations.show', $evaluation) }}" class="icon-btn" title="Lihat hasil">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                    @if (can_view('reports'))
                                        <a href="{{ route('reports.show', $evaluation) }}" class="icon-btn" title="Laporan">
                                            <x-icon name="file" class="h-4 w-4" />
                                        </a>
                                    @endif
                                    @if (can_manage('evaluations'))
                                        <form method="POST" action="{{ route('evaluations.destroy', $evaluation) }}" data-confirm="Hapus evaluasi {{ $evaluation->name }}? Hasil dapat dipulihkan dari Riwayat Aktivitas.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600" title="Hapus">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <x-icon name="chart" class="h-5 w-5" />
                                    </span>
                                    <p class="mt-3 text-sm font-medium text-slate-700">Belum ada evaluasi</p>
                                    <p class="mt-1 text-xs text-slate-500">Jalankan evaluasi pertama untuk membandingkan supplier.</p>
                                    @if (can_manage('evaluations'))
                                        <a href="{{ route('evaluations.create') }}" class="btn btn-primary btn-sm mt-4">
                                            <x-icon name="plus" class="h-3.5 w-3.5" /> Mulai evaluasi
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($evaluations->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $evaluations->links() }}</div>
        @endif
    </div>
@endsection
