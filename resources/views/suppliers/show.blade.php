@extends('layouts.app')

@section('title', $supplier->name)
@section('heading', $supplier->name)
@section('subheading', $supplier->code.' · '.($supplier->contact_person ?: 'Tanpa kontak'))

@section('actions')
    @if (can_manage('suppliers'))
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm">
            <x-icon name="edit" class="h-3.5 w-3.5" /> Edit
        </a>
    @endif
@endsection

@section('content')
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-bold text-white" style="background: {{ brand_color() }}">
                            {{ app_initials($supplier->name) }}
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $supplier->name }}</h2>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span class="badge badge-slate">{{ $supplier->code }}</span>
                                <span class="badge {{ $supplier->is_active ? 'badge-green' : 'badge-slate' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right text-[11px] text-slate-400">
                        <p>Ditambahkan {{ fmt_date($supplier->created_at, false) }}</p>
                        <p>Diperbarui {{ fmt_date($supplier->updated_at, false) }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="flex items-start gap-2.5">
                        <x-icon name="mail" class="mt-0.5 h-4 w-4 text-slate-400" />
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Email</p>
                            <p class="text-xs text-slate-700">{{ $supplier->email ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <x-icon name="phone" class="mt-0.5 h-4 w-4 text-slate-400" />
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Telepon</p>
                            <p class="text-xs text-slate-700">{{ $supplier->phone ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5 sm:col-span-2">
                        <x-icon name="map-pin" class="mt-0.5 h-4 w-4 text-slate-400" />
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-slate-400">Alamat</p>
                            <p class="text-xs leading-relaxed text-slate-700">{{ $supplier->address ?: '—' }}</p>
                        </div>
                    </div>
                </div>

                @if ($supplier->notes)
                    <div class="mt-5 rounded-lg bg-slate-50 px-4 py-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Catatan</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ $supplier->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="section-title">Nilai kriteria</h3>
                        <p class="muted">Nilai mentah yang dipakai dalam perhitungan MCDM</p>
                    </div>
                </div>
                <div class="grid gap-3 p-5 sm:grid-cols-2">
                    @forelse ($criteria as $criterion)
                        @php($value = $supplier->scoreFor($criterion->id))
                        <div class="rounded-xl border border-slate-200 px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-xs font-medium text-slate-700">{{ $criterion->name }}</p>
                                <span class="badge {{ $criterion->isCost() ? 'badge-amber' : 'badge-green' }}">{{ $criterion->isCost() ? 'cost' : 'benefit' }}</span>
                            </div>
                            <p class="mt-2 text-lg font-semibold text-slate-900">
                                {{ $value === null ? '—' : fmt_num($value) }}
                                <span class="text-[11px] font-normal text-slate-400">{{ $criterion->unit }}</span>
                            </p>
                            <p class="mt-0.5 text-[10px] text-slate-400">Bobot {{ fmt_num($criterion->weight, 1) }}%</p>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-slate-400 sm:col-span-2">Belum ada kriteria aktif.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h3 class="section-title">Riwayat evaluasi</h3>
                        <p class="muted">Posisi supplier dalam evaluasi yang pernah dijalankan</p>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($history as $result)
                        <a href="{{ route('evaluations.show', $result->evaluation) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-medium text-slate-800">{{ $result->evaluation->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ strtoupper($result->evaluation->method) }} · {{ fmt_date($result->evaluation->created_at, false) }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="badge {{ $result->rank === 1 ? 'badge-brand' : 'badge-slate' }}">Peringkat #{{ $result->rank }}</span>
                                <span class="text-xs font-semibold tabular-nums text-slate-700">{{ fmt_num($result->score) }}</span>
                            </div>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-400">Belum pernah masuk dalam evaluasi.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card card-pad">
                <h3 class="section-title">Ringkasan</h3>
                <dl class="mt-4 space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Kriteria terisi</dt>
                        <dd class="font-semibold text-slate-800">{{ $supplier->scores->count() }}/{{ $criteria->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Evaluasi diikuti</dt>
                        <dd class="font-semibold text-slate-800">{{ $history->count() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Status</dt>
                        <dd><span class="badge {{ $supplier->is_active ? 'badge-green' : 'badge-slate' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span></dd>
                    </div>
                </dl>
            </div>

            <div class="card card-pad">
                <h3 class="section-title">Aksi</h3>
                <div class="mt-3 space-y-2">
                    @if (can_manage('suppliers'))
                        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary w-full justify-start">
                            <x-icon name="edit" class="h-4 w-4" /> Edit data supplier
                        </a>
                    @endif
                    @if (can_manage('evaluations'))
                        <a href="{{ route('evaluations.create') }}" class="btn btn-secondary w-full justify-start">
                            <x-icon name="chart" class="h-4 w-4" /> Jalankan evaluasi
                        </a>
                    @endif
                </div>
            </div>

            @if (can_manage('suppliers'))
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs font-semibold text-rose-800">Hapus supplier</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-rose-700/80">
                        Data tidak langsung hilang permanen. Anda dapat memulihkannya dari Riwayat Aktivitas.
                    </p>
                    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="mt-3" data-confirm="Hapus supplier {{ $supplier->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger-soft btn-sm w-full">
                            <x-icon name="trash" class="h-3.5 w-3.5" /> Hapus supplier
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection
