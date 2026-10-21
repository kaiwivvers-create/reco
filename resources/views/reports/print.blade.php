@php($appName = setting('app_name', 'SuppliRank'))
<!DOCTYPE html>
<html lang="id" style="--brand: {{ brand_color() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak · {{ $evaluation->name }} · {{ $appName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="no-print sticky top-0 z-10 border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-2">
                <x-icon name="print" class="h-4 w-4 text-slate-500" />
                <div>
                    <p class="text-xs font-semibold text-slate-800">Mode cetak laporan</p>
                    <p class="text-[10px] text-slate-500">{{ $evaluation->name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('reports.show', $evaluation) }}" class="btn btn-secondary btn-sm">Kembali ke pratinjau</a>
                <button type="button" class="btn btn-primary btn-sm" data-print>
                    <x-icon name="print" class="h-3.5 w-3.5" /> Cetak / Simpan PDF
                </button>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
        <div class="print-sheet rounded-xl border border-slate-200 bg-white p-6 sm:p-10">
            @include('reports._document', [
                'evaluation' => $evaluation,
                'results' => $results,
                'criteria' => $criteria,
                'explanation' => $explanation,
                'company' => $company,
            ])
        </div>
        <p class="no-print mt-3 text-center text-[10px] text-slate-400">
            Gunakan dialog cetak browser untuk mencetak atau menyimpan sebagai PDF. Pilih orientasi landscape.
        </p>
    </div>
</body>
</html>
