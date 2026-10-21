<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CriterionController extends Controller
{
    public function index()
    {
        $criteria = Criterion::ordered()->withCount('scores')->get();
        $totalWeight = (float) $criteria->where('is_active', true)->sum('weight');

        return view('criteria.index', compact('criteria', 'totalWeight'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('criteria', 'code')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:benefit,cost'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'weight.min' => 'Bobot harus lebih besar dari 0.',
        ]);

        $criterion = Criterion::create([
            'code' => $data['code'] ?: Criterion::nextCode(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'weight' => $data['weight'],
            'unit' => $data['unit'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $data['sort_order'] ?? ((int) Criterion::max('sort_order') + 1),
        ]);

        ActivityLogger::log('created', 'criterion', $criterion->id, $criterion->name, null, $this->snapshot($criterion), 'Kriteria baru ditambahkan');

        return redirect()->route('criteria.index')->with('success', 'Kriteria "'.$criterion->name.'" berhasil ditambahkan.');
    }

    public function update(Request $request, Criterion $criterion)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('criteria', 'code')->ignore($criterion->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:benefit,cost'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $old = $this->snapshot($criterion);
        $criterion->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'weight' => $data['weight'],
            'unit' => $data['unit'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? $criterion->sort_order,
        ]);

        ActivityLogger::log('updated', 'criterion', $criterion->id, $criterion->name, $old, $this->snapshot($criterion), 'Kriteria diperbarui');

        return redirect()->route('criteria.index')->with('success', 'Kriteria berhasil diperbarui.');
    }

    public function updateWeights(Request $request)
    {
        $data = $request->validate([
            'weights' => ['required', 'array', 'min:1'],
            'weights.*' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ], [
            'weights.*.min' => 'Setiap bobot minimal 0,01.',
        ]);

        $updated = 0;

        foreach ($data['weights'] as $id => $weight) {
            $criterion = Criterion::find($id);
            if (! $criterion || (float) $criterion->weight === (float) $weight) {
                continue;
            }

            $old = $this->snapshot($criterion);
            $criterion->update(['weight' => $weight]);
            $updated++;

            ActivityLogger::log(
                'updated',
                'criterion',
                $criterion->id,
                $criterion->name,
                $old,
                $this->snapshot($criterion),
                'Bobot kriteria diubah dari '.$old['weight'].' menjadi '.$weight
            );
        }

        return redirect()->route('criteria.index')
            ->with('success', $updated > 0 ? $updated.' bobot kriteria berhasil diperbarui.' : 'Tidak ada bobot yang berubah.');
    }

    public function destroy(Criterion $criterion)
    {
        $old = $this->snapshot($criterion);
        $name = $criterion->name;
        $criterion->delete();

        ActivityLogger::log('deleted', 'criterion', $criterion->id, $name, $old, null, 'Kriteria dihapus');

        return redirect()->route('criteria.index')
            ->with('success', 'Kriteria "'.$name.'" dihapus. Nilai supplier pada kriteria ini tidak lagi dipakai dalam perhitungan.');
    }

    protected function snapshot(Criterion $criterion): array
    {
        return ActivityLogger::snapshot($criterion);
    }
}
