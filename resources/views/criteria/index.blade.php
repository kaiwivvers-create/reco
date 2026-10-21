@extends('layouts.app')

@section('title', 'Kriteria & Bobot')
@section('heading', 'Kriteria & Bobot')
@section('subheading', 'Tentukan faktor penilaian dan seberapa penting faktor tersebut')

@section('content')
    @php
        $editing = request('edit') ? $criteria->firstWhere('id', (int) request('edit')) : null;
        $formAction = $editing ? route('criteria.update', $editing) : route('criteria.store');
    @endphp

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="card card-pad">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Kriteria</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $criteria->count() }}</p>
                    <p class="text-[11px] text-slate-500">{{ $criteria->where('is_active', true)->count() }} aktif</p>
                </div>
                <div class="card card-pad">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Total bobot</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ fmt_num($totalWeight, 1) }}%</p>
                    <p class="text-[11px] text-slate-500">dinormalisasi saat evaluasi</p>
                </div>
                <div class="card card-pad">
                    <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">Nilai terisi</p>
                    <p class="mt-1 text-xl font-semibold text-slate-900">{{ $criteria->sum('scores_count') }}</p>
                    <p class="text-[11px] text-slate-500">pasangan supplier–kriteria</p>
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="section-title">Daftar kriteria</h3>
                        <p class="muted">Bobot dapat diubah langsung; total tidak wajib 100 karena sistem menormalisasi.</p>
                    </div>
                    @if (can_manage('criteria'))
                        <button type="submit" form="criteria-weights-form" class="btn btn-secondary btn-sm">
                            <x-icon name="save" class="h-3.5 w-3.5" /> Simpan bobot
                        </button>
                    @endif
                </div>

                <form id="criteria-weights-form" method="POST" action="{{ route('criteria.weights') }}">
                    @csrf
                    @method('PUT')
                </form>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kriteria</th>
                                <th>Tipe</th>
                                <th>Satuan</th>
                                <th class="w-28">Bobot</th>
                                <th class="text-right">Porsi</th>
                                <th class="text-right">Dipakai</th>
                                <th class="w-24 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($criteria as $criterion)
                                <tr class="{{ $criterion->is_active ? '' : 'opacity-60' }}">
                                    <td>
                                        <div class="flex items-start gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-[13px] font-medium text-slate-800">{{ $criterion->name }}</p>
                                                <p class="mt-0.5 line-clamp-2 max-w-xs text-[10px] leading-relaxed text-slate-400">
                                                    {{ $criterion->code }}{{ $criterion->description ? ' · '.$criterion->description : '' }}
                                                </p>
                                            </div>
                                            @unless ($criterion->is_active)
                                                <span class="badge badge-slate">nonaktif</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $criterion->isCost() ? 'badge-amber' : 'badge-green' }}">{{ $criterion->type }}</span>
                                    </td>
                                    <td class="text-xs text-slate-600">{{ $criterion->unit ?: '—' }}</td>
                                    <td>
                                        @if (can_manage('criteria'))
                                            <input type="number" step="0.01" min="0.01" max="100" name="weights[{{ $criterion->id }}]"
                                                   value="{{ $criterion->weight }}" form="criteria-weights-form"
                                                   class="input py-1.5 text-xs" aria-label="Bobot {{ $criterion->name }}">
                                        @else
                                            <span class="text-xs font-semibold text-slate-700">{{ fmt_num($criterion->weight, 1) }}%</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <span class="text-xs font-semibold tabular-nums text-slate-700">
                                            {{ $criterion->is_active && $totalWeight > 0 ? fmt_num(($criterion->weight / $totalWeight) * 100, 1).'%' : '—' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="badge badge-slate">{{ $criterion->scores_count }} supplier</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            @if (can_manage('criteria'))
                                                <a href="{{ route('criteria.index', ['edit' => $criterion->id]) }}" class="icon-btn" title="Edit">
                                                    <x-icon name="edit" class="h-4 w-4" />
                                                </a>
                                                <form method="POST" action="{{ route('criteria.destroy', $criterion) }}" data-confirm="Hapus kriteria {{ $criterion->name }}? Nilai supplier pada kriteria ini tidak akan dipakai lagi.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="icon-btn text-rose-500 hover:bg-rose-50 hover:text-rose-600" title="Hapus">
                                                        <x-icon name="trash" class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-[10px] text-slate-400">read only</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-xs text-slate-400">
                                        Belum ada kriteria. Tambahkan kriteria pertama di panel sebelah.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($criteria->isNotEmpty())
                    <div class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500">
                        Total bobot kriteria aktif: <span class="font-semibold text-slate-700">{{ fmt_num($totalWeight, 1) }}%</span>.
                        Sistem membagi bobot dengan total tersebut, sehingga perbandingan tetap proporsional.
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                <span class="font-semibold text-slate-700">Benefit vs cost.</span>
                Pilih <span class="font-medium text-emerald-700">benefit</span> bila nilai lebih besar lebih baik (kualitas, keandalan).
                Pilih <span class="font-medium text-amber-700">cost</span> bila nilai lebih kecil lebih baik (harga, waktu pengiriman).
            </div>
        </div>

        @if (can_manage('criteria'))
            <div>
                <div class="card card-pad lg:sticky lg:top-24">
                    <div class="flex items-center justify-between">
                        <h3 class="section-title">{{ $editing ? 'Edit kriteria' : 'Tambah kriteria' }}</h3>
                        @if ($editing)
                            <a href="{{ route('criteria.index') }}" class="text-[11px] text-slate-500 hover:text-slate-800">Batal edit</a>
                        @endif
                    </div>

                    <form method="POST" action="{{ $formAction }}" class="mt-4 space-y-3.5">
                        @csrf
                        @if ($editing)
                            @method('PUT')
                        @endif

                        <div>
                            <label class="label" for="name">Nama kriteria</label>
                            <input id="name" name="name" class="input" required maxlength="100" value="{{ old('name', $editing->name ?? '') }}" placeholder="Contoh: Harga Penawaran">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label" for="code">Kode</label>
                                <input id="code" name="code" class="input" maxlength="30" value="{{ old('code', $editing->code ?? '') }}" placeholder="{{ \App\Models\Criterion::nextCode() }}">
                            </div>
                            <div>
                                <label class="label" for="unit">Satuan</label>
                                <input id="unit" name="unit" class="input" maxlength="30" value="{{ old('unit', $editing->unit ?? '') }}" placeholder="Rp, hari, skor">
                            </div>
                        </div>

                        <div>
                            <label class="label" for="type">Tipe kriteria</label>
                            <select id="type" name="type" class="select">
                                <option value="benefit" @selected(old('type', $editing->type ?? 'benefit') === 'benefit')>Benefit — semakin besar semakin baik</option>
                                <option value="cost" @selected(old('type', $editing->type ?? '') === 'cost')>Cost — semakin kecil semakin baik</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label" for="weight">Bobot</label>
                                <input id="weight" name="weight" type="number" step="0.01" min="0.01" max="100" required class="input" value="{{ old('weight', $editing->weight ?? 25) }}">
                            </div>
                            <div>
                                <label class="label" for="sort_order">Urutan</label>
                                <input id="sort_order" name="sort_order" type="number" min="0" max="999" class="input" value="{{ old('sort_order', $editing->sort_order ?? (int) $criteria->max('sort_order') + 1) }}">
                            </div>
                        </div>

                        <div>
                            <label class="label" for="description">Deskripsi</label>
                            <textarea id="description" name="description" rows="3" maxlength="500" class="textarea" placeholder="Bagaimana kriteria ini diukur?">{{ old('description', $editing->description ?? '') }}</textarea>
                        </div>

                        <label class="flex items-center gap-2 text-xs text-slate-600">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $editing->is_active ?? true))>
                            Aktif dipakai dalam evaluasi
                        </label>

                        <button type="submit" class="btn btn-primary w-full">
                            <x-icon name="save" class="h-4 w-4" /> {{ $editing ? 'Simpan perubahan' : 'Tambah kriteria' }}
                        </button>
                    </form>

                    <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                        <span class="font-medium text-slate-700">Bobot tidak harus 100.</span>
                        Contoh: bobot 30, 30, 25, 15 akan dinormalisasi menjadi 30%, 30%, 25%, 15% dari total.
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
