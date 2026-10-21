@extends('layouts.app')

@section('title', 'Laporan · '.$evaluation->name)
@section('heading', 'Laporan Evaluasi')
@section('subheading', $evaluation->name)

@section('actions')
    <div class="flex items-center gap-1.5">
        <a href="{{ route('reports.pdf', $evaluation) }}" class="btn btn-secondary btn-sm">
            <x-icon name="download" class="h-3.5 w-3.5" /> <span class="hidden md:inline">PDF</span>
        </a>
        <a href="{{ route('reports.excel', $evaluation) }}" class="btn btn-secondary btn-sm">
            <x-icon name="table" class="h-3.5 w-3.5" /> <span class="hidden md:inline">Excel</span>
        </a>
        <a href="{{ route('reports.print', $evaluation) }}" target="_blank" class="btn btn-secondary btn-sm">
            <x-icon name="print" class="h-3.5 w-3.5" /> <span class="hidden md:inline">Print</span>
        </a>
    </div>
@endsection

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('evaluations.show', $evaluation) }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-slate-800">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Kembali ke hasil evaluasi
        </a>
        <p class="text-[10px] text-slate-400">Pratinjau laporan. Gunakan tombol di atas untuk mengunduh atau mencetak.</p>
    </div>

    <div class="card print-sheet mx-auto max-w-5xl p-6 sm:p-10">
        @include('reports._document', [
            'evaluation' => $evaluation,
            'results' => $results,
            'criteria' => $criteria,
            'explanation' => $explanation,
            'company' => $company,
        ])
    </div>
@endsection
