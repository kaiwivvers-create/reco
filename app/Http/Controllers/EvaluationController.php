<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\Supplier;
use App\Services\AiNarrativeService;
use App\Services\EvaluationService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluations,
    ) {
    }

    public function index(Request $request)
    {
        $evaluations = Evaluation::with('results', 'creator')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->string('method')))
            ->latest('id')
            ->paginate(8)
            ->withQueryString();

        return view('evaluations.index', compact('evaluations'));
    }

    public function create()
    {
        $suppliers = Supplier::with('scores')->where('is_active', true)->orderBy('name')->get();
        $criteria = Criterion::active()->ordered()->get();

        return view('evaluations.create', [
            'suppliers' => $suppliers,
            'criteria' => $criteria,
            'defaultMethod' => setting('default_method', 'saw'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'method' => ['required', 'in:saw,topsis'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'supplier_ids' => ['required', 'array', 'min:2'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', 'exists:suppliers,id'],
            'criteria_ids' => ['required', 'array', 'min:2'],
            'criteria_ids.*' => ['required', 'integer', 'distinct', 'exists:criteria,id'],
            'weights' => ['required', 'array'],
            'weights.*' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ], [
            'supplier_ids.min' => 'Pilih minimal dua supplier untuk dibandingkan.',
            'criteria_ids.min' => 'Pilih minimal dua kriteria penilaian.',
            'weights.*.min' => 'Bobot kriteria harus lebih besar dari 0.',
        ]);

        $criteriaModels = Criterion::whereIn('id', $data['criteria_ids'])->ordered()->get();
        $supplierModels = Supplier::with('scores')->whereIn('id', $data['supplier_ids'])->get();

        if ($supplierModels->count() !== count($data['supplier_ids'])) {
            return back()->withInput()->withErrors([
                'supplier_ids' => 'Salah satu supplier yang dipilih sudah tidak tersedia. Muat ulang halaman dan coba lagi.',
            ]);
        }

        $missing = [];
        foreach ($supplierModels as $supplier) {
            foreach ($criteriaModels as $criterion) {
                if ($supplier->scoreFor($criterion->id) === null) {
                    $missing[] = $supplier->name.' — '.$criterion->name;
                }
            }
        }

        if ($missing !== []) {
            $preview = implode(', ', array_slice($missing, 0, 3));
            $more = count($missing) > 3 ? ' dan '.(count($missing) - 3).' nilai lainnya' : '';

            return back()->withInput()->withErrors([
                'supplier_ids' => 'Nilai supplier belum lengkap: '.$preview.$more
                    .'. Lengkapi data supplier pada kriteria terpilih sebelum menjalankan evaluasi.',
            ]);
        }

        $criteria = $criteriaModels->map(fn (Criterion $criterion) => [
            'id' => $criterion->id,
            'code' => $criterion->code,
            'name' => $criterion->name,
            'type' => $criterion->type,
            'weight' => (float) ($data['weights'][$criterion->id] ?? $criterion->weight),
            'unit' => $criterion->unit,
        ])->values()->all();

        $evaluation = $this->evaluations->persist([
            'name' => $data['name'],
            'method' => $data['method'],
            'notes' => $data['notes'] ?? null,
            'criteria' => $criteria,
            'supplier_ids' => $data['supplier_ids'],
        ], auth()->id());

        $winner = $evaluation->results->firstWhere('rank', 1);

        ActivityLogger::log(
            'created',
            'evaluation',
            $evaluation->id,
            $evaluation->name,
            null,
            [
                'name' => $evaluation->name,
                'method' => $evaluation->method,
                'criteria' => $criteria,
                'supplier_ids' => $data['supplier_ids'],
            ],
            'Evaluasi dijalankan. Pemenang: '.($winner?->supplier_name ?? '-')
        );

        return redirect()->route('evaluations.show', $evaluation)
            ->with('success', 'Evaluasi selesai. '.($winner?->supplier_name ?? 'Supplier terbaik').' menempati peringkat pertama.');
    }

    public function show(Evaluation $evaluation)
    {
        $evaluation->load('results.supplier', 'creator');
        $explanation = $this->evaluations->explanationFor($evaluation);

        return view('evaluations.show', compact('evaluation', 'explanation'));
    }

    public function destroy(Evaluation $evaluation)
    {
        $old = [
            'name' => $evaluation->name,
            'method' => $evaluation->method,
            'criteria_snapshot' => $evaluation->criteria_snapshot,
            'supplier_ids' => $evaluation->supplier_ids,
        ];
        $name = $evaluation->name;
        $evaluation->delete();

        ActivityLogger::log('deleted', 'evaluation', $evaluation->id, $name, $old, null, 'Evaluasi dihapus (dapat dipulihkan)');

        return redirect()->route('evaluations.index')
            ->with('success', 'Evaluasi "'.$name.'" dipindahkan ke data terhapus.');
    }

    public function aiNarrative(Request $request, Evaluation $evaluation, AiNarrativeService $ai)
    {
        $explanation = $this->evaluations->explanationFor($evaluation);

        if (! $ai->configured()) {
            return response()->json([
                'message' => 'Fitur narasi AI belum aktif. Aktifkan di Pengaturan Aplikasi dan isi API key.',
            ], 422);
        }

        $winner = $explanation['winner'] ?? null;
        if (! $winner) {
            return response()->json(['message' => 'Evaluasi ini belum memiliki hasil untuk dijelaskan.'], 422);
        }

        $data = [
            'evaluasi' => $evaluation->name,
            'metode' => strtoupper($evaluation->method),
            'peringkat' => collect($explanation['ranking'] ?? [])->map(fn ($r) => [
                'supplier' => $r['name'],
                'skor' => round($r['score'], 2),
                'peringkat' => $r['rank'],
            ])->all(),
            'pemenang' => $winner['name'],
            'alasan_deterministik' => $winner['reasons'] ?? [],
            'kontrafaktual' => $explanation['comparison']['counterfactual']['text'] ?? null,
            'sensitivitas' => $explanation['comparison']['sensitivity']['text'] ?? null,
        ];

        try {
            $narrative = $ai->generate(
                'Anda adalah analis pengadaan senior. Tulis narasi eksekutif dalam Bahasa Indonesia (3-5 kalimat) '
                .'yang menjelaskan rekomendasi supplier. Gunakan HANYA angka dan fakta dari data yang diberikan. '
                .'Jangan mengarang data baru. Sebutkan pemenang, alasan utama, selisih skor, dan satu catatan risiko.',
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}'
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['narrative' => $narrative]);
    }

}
