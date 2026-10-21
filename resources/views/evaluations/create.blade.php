@extends('layouts.app')

@section('title', 'Evaluasi Baru')
@section('heading', 'Evaluasi Baru')
@section('subheading', 'Pilih supplier, kriteria, bobot, dan metode perhitungan')

@section('actions')
    <a href="{{ route('evaluations.index') }}" class="btn btn-secondary btn-sm">
        <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Kembali
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('evaluations.store') }}" class="grid gap-5 lg:grid-cols-3">
        @csrf

        <div class="space-y-5 lg:col-span-2">
            <div class="card card-pad">
                <div class="flex items-center gap-2">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white" style="background: {{ brand_color() }}">1</span>
                    <h3 class="section-title">Informasi evaluasi</h3>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="name">Nama evaluasi</label>
                        <input id="name" name="name" class="input" required maxlength="120" value="{{ old('name', 'Evaluasi Supplier '.now()->locale('id')->translatedFormat('F Y')) }}" placeholder="Contoh: Pengadaan Peralatan Kantor Q1 2026">
                        @error('name')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="notes">Catatan (opsional)</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="textarea" placeholder="Konteks pengadaan, sumber penawaran, atau asumsi penilaian.">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <span class="label">Metode perhitungan</span>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['saw' => ['SAW', 'Simple Additive Weighting', 'Menjumlahkan bobot × nilai ternormalisasi. Cepat dan mudah dijelaskan.'], 'topsis' => ['TOPSIS', 'Ideal Solution', 'Membandingkan jarak ke solusi terbaik dan terburuk. Baik untuk kompromi antar kriteria.']] as $value => $meta)
                            <label class="choice-card">
                                <input type="radio" name="method" value="{{ $value }}" class="sr-only" @checked(old('method', $defaultMethod) === $value)>
                                <span class="flex items-start justify-between gap-2">
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-800">{{ $meta[0] }}</span>
                                        <span class="block text-[10px] uppercase tracking-wide text-slate-400">{{ $meta[1] }}</span>
                                    </span>
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-slate-300">
                                        <span class="h-2 w-2 rounded-full bg-[var(--brand)]"></span>
                                    </span>
                                </span>
                                <span class="mt-2 block text-[11px] leading-relaxed text-slate-500">{{ $meta[2] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('method')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white" style="background: {{ brand_color() }}">2</span>
                        <div>
                            <h3 class="section-title">Pilih supplier</h3>
                            <p class="muted">Minimal dua supplier untuk perbandingan</p>
                        </div>
                    </div>
                    <span class="badge badge-brand"><span data-supplier-count>0</span> dipilih</span>
                </div>

                @error('supplier_ids')<p class="hint text-rose-600">{{ $message }}</p>@enderror

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @forelse ($suppliers as $supplier)
                        @php($filled = $supplier->scores->count())
                        <label class="choice-card">
                            <input type="checkbox" name="supplier_ids[]" value="{{ $supplier->id }}" class="sr-only" data-supplier-check
                                   @checked(in_array($supplier->id, old('supplier_ids', $suppliers->pluck('id')->all())))>
                            <span class="flex items-start justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[10px] font-bold text-indigo-600">
                                        {{ app_initials($supplier->name) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-xs font-semibold text-slate-800">{{ $supplier->name }}</span>
                                        <span class="block text-[10px] text-slate-400">{{ $supplier->code }}</span>
                                    </span>
                                </span>
                                <span class="badge {{ $filled >= $criteria->count() && $criteria->count() > 0 ? 'badge-green' : 'badge-amber' }}">
                                    {{ $filled }}/{{ $criteria->count() }} nilai
                                </span>
                            </span>
                        </label>
                    @empty
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] text-amber-800 sm:col-span-2">
                            Belum ada supplier aktif.
                            <a href="{{ route('suppliers.create') }}" class="font-semibold underline">Tambah supplier</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card card-pad" data-weight-scope>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white" style="background: {{ brand_color() }}">3</span>
                        <div>
                            <h3 class="section-title">Kriteria & bobot</h3>
                            <p class="muted">Pilih kriteria yang relevan dan atur kepentingannya</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-equalize>
                        <x-icon name="scale" class="h-3.5 w-3.5" /> Bagi rata
                    </button>
                </div>

                @error('criteria_ids')<p class="hint text-rose-600">{{ $message }}</p>@enderror
                @error('weights')<p class="hint text-rose-600">{{ $message }}</p>@enderror

                <div class="mt-4 space-y-2">
                    @forelse ($criteria as $criterion)
                        <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5" data-criterion-row>
                            <input type="checkbox" name="criteria_ids[]" value="{{ $criterion->id }}" class="checkbox" data-criteria-enabled
                                   @checked(in_array($criterion->id, old('criteria_ids', $criteria->pluck('id')->all())))>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="truncate text-xs font-medium text-slate-800">{{ $criterion->name }}</span>
                                    <span class="badge {{ $criterion->isCost() ? 'badge-amber' : 'badge-green' }}">{{ $criterion->type }}</span>
                                </div>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ $criterion->unit ?: 'tanpa satuan' }}{{ $criterion->description ? ' · '.$criterion->description : '' }}</p>
                            </div>
                            <div class="flex w-24 shrink-0 items-center gap-1">
                                <input type="number" step="0.01" min="0.01" max="100" name="weights[{{ $criterion->id }}]"
                                       value="{{ old('weights.'.$criterion->id, $criterion->weight) }}"
                                       class="input py-1.5 text-right text-xs" data-weight-input aria-label="Bobot {{ $criterion->name }}">
                                <span class="text-[11px] text-slate-400">%</span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] text-amber-800">
                            Belum ada kriteria aktif.
                            <a href="{{ route('criteria.index') }}" class="font-semibold underline">Buat kriteria</a>
                        </div>
                    @endforelse
                </div>

                <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2.5">
                    <span class="text-[11px] text-slate-500">Total bobot terpilih</span>
                    <span class="text-xs font-semibold text-slate-800"><span data-weight-total>{{ fmt_num($criteria->sum('weight'), 1) }}</span>%</span>
                </div>
                <p data-weight-warning class="mt-1 hidden text-[10px] text-amber-600">
                    Total tidak harus 100%; sistem akan menormalisasi bobot secara proporsional.
                </p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card card-pad lg:sticky lg:top-24">
                <h3 class="section-title">Ringkasan</h3>
                <dl class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Supplier dipilih</dt>
                        <dd class="font-semibold text-slate-800"><span data-supplier-count>0</span></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Kriteria tersedia</dt>
                        <dd class="font-semibold text-slate-800">{{ $criteria->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Metode</dt>
                        <dd class="font-semibold uppercase text-slate-800">{{ old('method', $defaultMethod) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Total bobot</dt>
                        <dd class="font-semibold text-slate-800"><span data-weight-total>{{ fmt_num($criteria->sum('weight'), 1) }}</span>%</dd>
                    </div>
                </dl>

                <div class="my-4 divider"></div>

                <div class="rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                    <span class="font-medium text-slate-700">Snapshot.</span>
                    Hasil evaluasi menyimpan salinan kriteria, bobot, dan nilai supplier saat dijalankan,
                    sehingga laporan lama tidak berubah ketika data master diperbarui. Pastikan nilai
                    seluruh supplier lengkap pada kriteria yang dipilih.
                </div>

                <button type="submit" class="btn btn-primary mt-4 w-full">
                    <x-icon name="play" class="h-4 w-4" /> Hitung &amp; simpan evaluasi
                </button>
                <p class="mt-2 text-center text-[10px] text-slate-400">Perhitungan dilakukan di server menggunakan data tersimpan.</p>
            </div>

            <div class="card card-pad">
                <h3 class="section-title">Tentang metode</h3>
                <div class="mt-3 space-y-3 text-[11px] leading-relaxed text-slate-500">
                    <p><span class="font-semibold text-slate-700">SAW</span> menormalkan tiap nilai (benefit: x/max, cost: min/x), mengalikan dengan bobot, lalu menjumlahkannya.</p>
                    <p><span class="font-semibold text-slate-700">TOPSIS</span> menormalkan vektor, menentukan solusi ideal positif/negatif, lalu mengukur kedekatan relatif.</p>
                    <p class="text-slate-400">Keduanya menghasilkan skor 0–100. Sistem menampilkan skor dan alasan, bukan hanya peringkat.</p>
                </div>
            </div>
        </div>
    </form>
@endsection
