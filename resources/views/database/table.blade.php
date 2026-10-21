@extends('layouts.app')

@section('title', 'Tabel '.$table)
@section('heading', 'Tabel '.$table)
@section('subheading', 'Pratinjau 25 baris per halaman · '.$rows->total().' total baris')

@section('actions')
    <a href="{{ route('database.export-table', $table) }}" class="btn btn-secondary btn-sm">
        <x-icon name="download" class="h-3.5 w-3.5" /> CSV
    </a>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('database.index') }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Kembali ke database
        </a>
        <p class="inline-flex items-center gap-1 text-[10px] text-amber-600">
            <x-icon name="alert" class="h-3.5 w-3.5" />
            Data dapat memuat informasi sensitif. Jangan bagikan tangkapan layar tabel pengguna.
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="badge badge-slate">{{ count($columns) }} kolom</span>
                <span class="badge badge-brand">{{ number_format($rows->total(), 0, ',', '.') }} baris</span>
                <span class="text-[10px] text-slate-400">Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }}</span>
            </div>
            <a href="{{ route('database.export-table', $table) }}" class="btn btn-secondary btn-xs">
                <x-icon name="download" class="h-3 w-3" /> Unduh CSV
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="table text-[10px]">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th class="whitespace-nowrap">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                @php($value = $row->{$column})
                                <td class="max-w-[260px] break-all align-top">
                                    @if ($value === null)
                                        <span class="text-slate-300">NULL</span>
                                    @else
                                        {{ \Illuminate\Support\Str::limit((string) $value, 160) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="py-10 text-center text-xs text-slate-400">
                                Tabel ini belum memiliki data.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </div>

    <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-[10px] leading-relaxed text-slate-500">
        Pratinjau ini hanya untuk keperluan inspeksi. Gunakan backup JSON untuk pemulihan penuh dan CSV untuk
        analisis di spreadsheet. Perubahan langsung pada tabel tidak disediakan demi menjaga integritas data.
    </div>
@endsection
