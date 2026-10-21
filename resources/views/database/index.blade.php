@extends('layouts.app')

@section('title', 'Database')
@section('heading', 'Database')
@section('subheading', 'Backup, ekspor, dan impor data aplikasi')

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Driver</p>
            <p class="mt-1 text-lg font-semibold uppercase text-slate-900">{{ $info['driver'] }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Nama database</p>
            <p class="mt-1 truncate text-sm font-semibold text-slate-900" title="{{ $info['database'] }}">{{ $info['database'] }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Tabel data</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ count($tables) }}</p>
        </div>
        <div class="card card-pad">
            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Total baris</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format($totalRows, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="card card-pad">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><x-icon name="download" class="h-3.5 w-3.5" /></span>
                <div>
                    <h3 class="section-title">Ekspor / backup</h3>
                    <p class="muted">Unduh seluruh data aplikasi</p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <a href="{{ route('database.export', 'json') }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-[var(--brand)] hover:bg-slate-50">
                    <span class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icon name="file" class="h-4 w-4" /></span>
                        <span>
                            <span class="block text-xs font-semibold text-slate-800">Backup JSON</span>
                            <span class="block text-[10px] text-slate-400">Direkomendasikan — dapat diimpor kembali</span>
                        </span>
                    </span>
                    <x-icon name="download" class="h-4 w-4 text-slate-400" />
                </a>
                <a href="{{ route('database.export', 'sql') }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-[var(--brand)] hover:bg-slate-50">
                    <span class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><x-icon name="database" class="h-4 w-4" /></span>
                        <span>
                            <span class="block text-xs font-semibold text-slate-800">SQL dump (INSERT)</span>
                            <span class="block text-[10px] text-slate-400">Untuk arsip dan pemindahan manual</span>
                        </span>
                    </span>
                    <x-icon name="download" class="h-4 w-4 text-slate-400" />
                </a>
            </div>

            <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-[10px] leading-relaxed text-slate-500">
                Backup mencakup supplier, kriteria, nilai, evaluasi, pengguna, pengaturan, dan riwayat aktivitas.
                Tabel framework seperti sesi dan cache tidak disertakan.
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><x-icon name="upload" class="h-3.5 w-3.5" /></span>
                <div>
                    <h3 class="section-title">Impor data</h3>
                    <p class="muted">Pulihkan dari berkas backup JSON</p>
                </div>
            </div>

            <form method="POST" action="{{ route('database.import') }}" enctype="multipart/form-data" class="mt-4 space-y-3"
                  data-confirm="Lanjutkan impor data? Mode 'Ganti' akan menghapus data yang ada pada tabel yang diimpor.">
                @csrf
                <div>
                    <label class="label" for="file">Berkas backup</label>
                    <input id="file" name="file" type="file" accept=".json,.txt,application/json" required
                           class="block w-full text-[11px] text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-[11px] file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <p class="hint">Berkas JSON hasil ekspor, maksimal 20 MB.</p>
                    @error('file')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <span class="label">Mode impor</span>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="choice-card">
                            <input type="radio" name="mode" value="replace" class="sr-only" checked>
                            <span class="block text-xs font-semibold text-slate-800">Ganti</span>
                            <span class="mt-1 block text-[10px] leading-relaxed text-slate-500">Hapus baris tabel terkait lalu impor ulang. Paling sesuai untuk restore penuh.</span>
                        </label>
                        <label class="choice-card">
                            <input type="radio" name="mode" value="merge" class="sr-only">
                            <span class="block text-xs font-semibold text-slate-800">Gabung</span>
                            <span class="mt-1 block text-[10px] leading-relaxed text-slate-500">Tambahkan data baru, lewati baris yang bentrok (ID/unique).</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="upload" class="h-4 w-4" /> Proses impor
                </button>
            </form>

            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[10px] leading-relaxed text-amber-800">
                <span class="font-semibold">Perhatian.</span>
                Mode ganti dapat menimpa data pengguna dan evaluasi. Buat backup terlebih dahulu. Setelah impor
                pengguna, sesi login dapat berakhir dan Anda perlu masuk kembali.
            </div>
            @if (session('import_detail'))
                <div class="mt-2 rounded-lg bg-slate-50 px-3 py-2 font-mono text-[10px] text-slate-500">{{ session('import_detail') }}</div>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="card overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="section-title">Tabel data</h3>
                    <p class="muted">Klik nama tabel untuk melihat isinya, atau unduh CSV</p>
                </div>
                <span class="badge badge-slate">{{ count($tables) }} tabel</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tabel</th>
                            <th class="text-right">Baris</th>
                            <th class="w-32 text-right">Ekspor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr>
                                <td>
                                    <a href="{{ route('database.table', $table) }}" class="inline-flex items-center gap-2 hover:text-[var(--brand)]">
                                        <x-icon name="table" class="h-3.5 w-3.5 text-slate-400" />
                                        <span class="font-mono text-[11px] font-medium text-slate-700">{{ $table }}</span>
                                        <x-icon name="arrow-right" class="h-3 w-3 text-slate-300" />
                                    </a>
                                </td>
                                <td class="text-right text-xs tabular-nums">{{ number_format($counts[$table] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-right">
                                    <a href="{{ route('database.export-table', $table) }}" class="btn btn-secondary btn-xs">
                                        <x-icon name="download" class="h-3 w-3" /> CSV
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="section-title">Riwayat backup</h3>
                <p class="muted">Ekspor dan impor terakhir</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($history as $item)
                    <div class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <span class="badge {{ $item->action === 'imported' ? 'badge-violet' : 'badge-blue' }}">{{ $item->actionLabel() }}</span>
                            <span class="truncate text-[11px] font-medium text-slate-700">{{ $item->label }}</span>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">{{ $item->user_name }} · {{ fmt_date($item->created_at) }}</p>
                        @if ($item->description)
                            <p class="mt-0.5 text-[10px] leading-relaxed text-slate-500">{{ \Illuminate\Support\Str::limit($item->description, 110) }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-xs text-slate-400">Belum ada aktivitas backup.</p>
                @endforelse
            </div>
            <div class="border-t border-slate-100 px-5 py-3">
                <a href="{{ route('activity.index', ['type' => 'database']) }}" class="text-[10px] font-medium text-[var(--brand)] hover:underline">Lihat semua riwayat</a>
            </div>
        </div>
    </div>
@endsection
