@extends('layouts.app')

@section('title', 'Data Supplier')
@section('heading', 'Data Supplier')
@section('subheading', 'Kelola profil supplier dan nilai mentah untuk setiap kriteria')

@section('actions')
    @if (can_manage('suppliers'))
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" class="h-3.5 w-3.5" /> Tambah supplier
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
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode, kontak…" class="input pl-9">
            </div>
            <select name="status" class="select sm:w-40" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
            @if (request()->filled('q') || request()->filled('status'))
                <a href="{{ route('suppliers.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
        <p class="text-[11px] text-slate-500">{{ $suppliers->total() }} supplier · {{ $scoreCount }} nilai kriteria</p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Kontak</th>
                        <th>Nilai Kriteria</th>
                        <th>Status</th>
                        <th class="w-24 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[10px] font-bold text-indigo-600">
                                        {{ app_initials($supplier->name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="block truncate text-[13px] font-semibold text-slate-800 hover:text-[var(--brand)]">
                                            {{ $supplier->name }}
                                        </a>
                                        <span class="text-[10px] text-slate-400">{{ $supplier->code }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-xs text-slate-600">{{ $supplier->contact_person ?: '—' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $supplier->email ?: ($supplier->phone ?: 'Belum ada kontak') }}</div>
                            </td>
                            <td>
                                <div class="flex max-w-md flex-wrap gap-1">
                                    @forelse ($criteria as $criterion)
                                        @php($value = $supplier->scoreFor($criterion->id))
                                        <span class="badge {{ $value === null ? 'badge-red' : 'badge-slate' }}" title="{{ $criterion->name }} ({{ $criterion->type }})">
                                            {{ \Illuminate\Support\Str::limit($criterion->name, 18) }}:
                                            {{ $value === null ? 'kosong' : fmt_num($value) }}{{ $criterion->unit ? ' '.$criterion->unit : '' }}
                                        </span>
                                    @empty
                                        <span class="text-[11px] text-slate-400">Belum ada kriteria</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <span class="badge {{ $supplier->is_active ? 'badge-green' : 'badge-slate' }}">
                                    {{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="icon-btn" title="Detail">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </a>
                                    @if (can_manage('suppliers'))
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="icon-btn" title="Edit">
                                            <x-icon name="edit" class="h-4 w-4" />
                                        </a>
                                        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" data-confirm="Hapus supplier {{ $supplier->name }}? Data masih dapat dipulihkan dari Riwayat Aktivitas.">
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
                            <td colspan="5">
                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <x-icon name="building" class="h-5 w-5" />
                                    </span>
                                    <p class="mt-3 text-sm font-medium text-slate-700">Belum ada supplier</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ request()->filled('q') ? 'Tidak ada hasil untuk pencarian tersebut.' : 'Tambahkan supplier beserta nilai kriteria untuk mulai membandingkan.' }}
                                    </p>
                                    @if (can_manage('suppliers'))
                                        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm mt-4">
                                            <x-icon name="plus" class="h-3.5 w-3.5" /> Tambah supplier
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($suppliers->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>

    @if ($criteria->isEmpty())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            <span class="font-semibold">Belum ada kriteria aktif.</span>
            Buat kriteria terlebih dahulu agar nilai supplier dapat diisi.
            @if (can_manage('criteria'))
                <a href="{{ route('criteria.index') }}" class="ml-1 font-semibold underline">Buka halaman kriteria</a>
            @endif
        </div>
    @endif
@endsection
