<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Services\EvaluationService;
use App\Services\McdmService;
use App\Support\PdfReport;
use App\Support\Settings;
use App\Support\XlsxReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(protected EvaluationService $evaluations)
    {
    }

    public function index(Request $request)
    {
        $evaluations = Evaluation::with('results', 'creator')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('reports.index', compact('evaluations'));
    }

    public function show(Evaluation $evaluation)
    {
        $report = $this->reportData($evaluation);

        return view('reports.show', $report);
    }

    public function print(Evaluation $evaluation)
    {
        $report = $this->reportData($evaluation);

        return view('reports.print', $report);
    }

    public function pdf(Evaluation $evaluation)
    {
        $report = $this->reportData($evaluation);
        $explanation = $report['explanation'];
        $criteria = $report['criteria'];
        $results = $report['results'];
        $winner = $explanation['winner'] ?? null;
        $comparison = $explanation['comparison'] ?? [];
        $runner = $results->firstWhere('rank', 2);

        $pdf = new PdfReport('landscape');
        $pdf->setTitle('Laporan Evaluasi Supplier — '.$evaluation->name);
        $pdf->setFooter((string) Settings::get('report_footer'));

        $pdf->heading('Laporan Evaluasi & Rekomendasi Supplier');
        $pdf->subheading((string) Settings::get('company_name').' · '.$evaluation->name);
        $pdf->metaLine('Metode', strtoupper($evaluation->method));
        $pdf->metaLine('Tanggal evaluasi', fmt_date($evaluation->created_at));
        $pdf->metaLine('Dibuat oleh', $evaluation->creator?->name ?? 'Sistem');
        $pdf->metaLine('Peserta', $results->count().' supplier · '.count($criteria).' kriteria');
        $pdf->spacer(6);

        $pdf->sectionTitle('1. Rekomendasi Utama');
        if ($winner) {
            $pdf->paragraph($winner['name'].' — skor '.fmt_num($winner['score']).' dari 100', 12, true);
            $pdf->paragraph($winner['summary'] ?? '');
            foreach (($winner['reasons'] ?? []) as $index => $reason) {
                $pdf->paragraph(($index + 1).'. '.$reason);
            }
        } else {
            $pdf->paragraph('Belum ada hasil rekomendasi.');
        }

        $pdf->sectionTitle('2. Peringkat Supplier');
        $pdf->table(
            ['Peringkat', 'Supplier', 'Skor (0-100)', 'Selisih dari Pemenang', 'Keterangan'],
            $results->map(function ($result) use ($results) {
                $top = $results->first();
                $gap = $top && $result->rank > 1 ? $top->score - $result->score : 0;

                return [
                    '#'.$result->rank,
                    $result->supplier_name,
                    fmt_num($result->score),
                    $result->rank === 1 ? '—' : fmt_num($gap),
                    $result->rank === 1 ? 'Direkomendasikan' : 'Alternatif',
                ];
            })->all(),
            [0.1, 0.32, 0.16, 0.22, 0.2],
            ['center', 'left', 'right', 'right', 'left']
        );

        if ($winner && $runner) {
            $pdf->sectionTitle('3. Perbandingan Pemenang vs Peringkat Kedua');
            $pdf->paragraph($winner['name'].' dibandingkan dengan '.$runner->supplier_name
                .' pada setiap kriteria. Selisih skor akhir: '.fmt_num($comparison['margin'] ?? 0).' poin.');
            $rows = collect($comparison['rows'] ?? [])->map(fn ($row) => [
                $row['name'].($row['type'] === 'cost' ? ' (cost)' : ' (benefit)'),
                fmt_num($row['winner_performance'], 1).'%',
                $row['winner_text'],
                $row['runner_text'],
                ucfirst($row['status']),
            ])->all();

            $pdf->table(
                ['Kriteria', 'Performa Pemenang', $winner['name'], $runner->supplier_name, 'Status'],
                $rows,
                [0.26, 0.15, 0.23, 0.23, 0.13],
                ['left', 'right', 'right', 'right', 'center']
            );
        }

        $pdf->sectionTitle((($winner && $runner) ? '4' : '3').'. Kontribusi Kriteria per Supplier');
        $contribRows = [];
        foreach ($results as $result) {
            $supplierXai = $explanation['suppliers'][$result->supplier_id] ?? null;
            foreach (($supplierXai['contributions'] ?? []) as $contribution) {
                $contribRows[] = [
                    $result->supplier_name,
                    $contribution['name'],
                    $contribution['raw_text'],
                    fmt_num($contribution['weight_pct'], 1).'%',
                    fmt_num($contribution['points'] ?? $contribution['contribution'] * 100),
                    fmt_num($contribution['share_pct'], 1).'%',
                ];
            }
        }
        $pdf->table(
            ['Supplier', 'Kriteria', 'Nilai', 'Bobot', 'Kontribusi (poin)', 'Porsi'],
            $contribRows,
            [0.24, 0.22, 0.18, 0.12, 0.12, 0.12],
            ['left', 'left', 'right', 'right', 'right', 'right']
        );

        $pdf->sectionTitle((($winner && $runner) ? '5' : '4').'. Analisis Explainable AI');
        if ($comparison['counterfactual']['text'] ?? null) {
            $pdf->paragraph('Kontrafaktual: '.$comparison['counterfactual']['text']);
        }
        if ($comparison['sensitivity']['text'] ?? null) {
            $pdf->paragraph('Sensitivitas bobot: '.$comparison['sensitivity']['text']);
        }
        if ($explanation['method_note'] ?? null) {
            $pdf->paragraph($explanation['method_note']);
        }

        $pdf->sectionTitle((($winner && $runner) ? '6' : '5').'. Penjelasan per Supplier');
        foreach ($results as $result) {
            $supplierXai = $explanation['suppliers'][$result->supplier_id] ?? null;
            $pdf->paragraph('#'.$result->rank.' '.$result->supplier_name.' — skor '.fmt_num($result->score), 9.5, true);
            $pdf->paragraph($supplierXai['narrative'] ?? 'Tidak ada narasi.');
        }

        $pdf->spacer(8);
        $pdf->paragraph('Catatan: rekomendasi ini dihasilkan dari bobot dan metode yang dipilih pengguna. '
            .'Sistem tidak mengasumsikan supplier termurah adalah yang terbaik; seluruh kriteria dihitung sesuai bobotnya.', 8, false);

        $filename = Str::slug($evaluation->name).'-'.now()->format('Ymd').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function excel(Evaluation $evaluation)
    {
        $report = $this->reportData($evaluation);
        $explanation = $report['explanation'];
        $criteria = $report['criteria'];
        $results = $report['results'];

        $xlsx = new XlsxReport();
        $top = $results->first();

        $xlsx->addSheet('Ranking', array_merge([
            ['Peringkat', 'Supplier', 'Skor', 'Selisih dari Pemenang', 'Metode', 'Keterangan'],
        ], $results->map(fn ($result) => [
            $result->rank,
            $result->supplier_name,
            round($result->score, 4),
            $top ? round($top->score - $result->score, 4) : 0,
            strtoupper($evaluation->method),
            $result->rank === 1 ? 'Direkomendasikan' : 'Alternatif',
        ])->all()), true, [10, 32, 12, 20, 12, 20]);

        $criteriaHeader = ['Supplier'];
        foreach ($criteria as $criterion) {
            $criteriaHeader[] = $criterion['name'].' ('.$criterion['type'].', '.fmt_num($criterion['weight'], 1).'%)';
        }
        $criteriaHeader[] = 'Skor';

        $matrixRows = [$criteriaHeader];
        foreach ($results as $result) {
            $row = [$result->supplier_name];
            foreach ($criteria as $criterion) {
                $row[] = $result->detail['matrix'][$criterion['id']]['raw'] ?? 0;
            }
            $row[] = round($result->score, 4);
            $matrixRows[] = $row;
        }
        $xlsx->addSheet('Matriks Keputusan', $matrixRows, true);

        $contributionRows = [['Supplier', 'Kriteria', 'Nilai', 'Bobot (%)', 'Kontribusi (poin)', 'Porsi Kontribusi (%)']];
        foreach ($results as $result) {
            $supplierXai = $explanation['suppliers'][$result->supplier_id] ?? null;
            foreach (($supplierXai['contributions'] ?? []) as $contribution) {
                $contributionRows[] = [
                    $result->supplier_name,
                    $contribution['name'],
                    $contribution['raw_text'],
                    round($contribution['weight_pct'], 2),
                    round($contribution['points'] ?? $contribution['contribution'] * 100, 4),
                    round($contribution['share_pct'], 2),
                ];
            }
        }
        $xlsx->addSheet('Kontribusi', $contributionRows, true, [28, 24, 18, 12, 14, 20]);

        $explanationRows = [['Supplier', 'Peringkat', 'Skor', 'Narasi', 'Kekuatan Utama', 'Catatan Perbaikan']];
        foreach ($results as $result) {
            $supplierXai = $explanation['suppliers'][$result->supplier_id] ?? null;
            $explanationRows[] = [
                $result->supplier_name,
                $result->rank,
                round($result->score, 4),
                $supplierXai['narrative'] ?? '',
                collect($supplierXai['strengths'] ?? [])->pluck('text')->implode("\n"),
                collect($supplierXai['weaknesses'] ?? [])->pluck('text')->implode("\n"),
            ];
        }
        $xlsx->addSheet('Penjelasan', $explanationRows, true, [28, 10, 10, 70, 60, 60]);

        $filename = Str::slug($evaluation->name).'-'.now()->format('Ymd').'.xlsx';

        return response($xlsx->output(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function reportData(Evaluation $evaluation): array
    {
        $evaluation->loadMissing('results', 'creator');

        return [
            'evaluation' => $evaluation,
            'results' => $evaluation->results->sortBy('rank')->values(),
            'criteria' => McdmService::criteriaFromSnapshot($evaluation->criteria_snapshot ?? []),
            'explanation' => $this->evaluations->explanationFor($evaluation),
            'company' => Settings::get('company_name'),
        ];
    }
}
