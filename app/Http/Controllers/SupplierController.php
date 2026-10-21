<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\EvaluationResult;
use App\Models\Supplier;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $criteria = Criterion::active()->ordered()->get();

        $suppliers = Supplier::with('scores')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status') === 'active'))
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString();

        $scoreCount = \App\Models\SupplierScore::count();

        return view('suppliers.index', compact('suppliers', 'criteria', 'scoreCount'));
    }

    public function create()
    {
        $criteria = Criterion::active()->ordered()->get();

        return view('suppliers.create', [
            'criteria' => $criteria,
            'supplier' => new Supplier(['code' => Supplier::nextCode(), 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $isActive = $request->boolean('is_active', true);

        $supplier = DB::transaction(function () use ($data, $isActive) {
            $supplier = Supplier::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $isActive,
            ]);

            $this->syncScores($supplier, $data['scores'] ?? []);

            return $supplier;
        });

        $supplier->load('scores');
        ActivityLogger::log('created', 'supplier', $supplier->id, $supplier->name, null, $this->snapshot($supplier), 'Supplier baru ditambahkan');

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier "'.$supplier->name.'" berhasil ditambahkan.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->load('scores.criterion');
        $criteria = Criterion::active()->ordered()->get();

        $history = EvaluationResult::with('evaluation')
            ->where('supplier_id', $supplier->id)
            ->latest('id')
            ->take(6)
            ->get();

        return view('suppliers.show', compact('supplier', 'criteria', 'history'));
    }

    public function edit(Supplier $supplier)
    {
        $supplier->load('scores');
        $criteria = Criterion::active()->ordered()->get();

        return view('suppliers.edit', compact('supplier', 'criteria'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validated($request, supplier: $supplier);
        $supplier->load('scores');
        $old = $this->snapshot($supplier);

        DB::transaction(function () use ($supplier, $data, $request) {
            $supplier->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncScores($supplier, $data['scores'] ?? []);
        });

        $supplier->load('scores');
        ActivityLogger::log('updated', 'supplier', $supplier->id, $supplier->name, $old, $this->snapshot($supplier), 'Data supplier diperbarui');

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Data supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->load('scores');
        $old = $this->snapshot($supplier);
        $name = $supplier->name;
        $supplier->delete();

        ActivityLogger::log('deleted', 'supplier', $supplier->id, $name, $old, null, 'Supplier dihapus (dapat dipulihkan)');

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier "'.$name.'" dipindahkan ke data terhapus. Pulihkan dari Riwayat Aktivitas.');
    }

    protected function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('suppliers', 'code')->ignore($supplier?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
        ], [
            'code.unique' => 'Kode supplier sudah digunakan (termasuk data yang pernah dihapus).',
            'name.required' => 'Nama supplier wajib diisi.',
        ]);
    }

    protected function syncScores(Supplier $supplier, array $scores): void
    {
        $validCriteria = Criterion::pluck('id')->all();

        foreach ($scores as $criterionId => $value) {
            if (! in_array((int) $criterionId, $validCriteria, true) || $value === null || $value === '') {
                continue;
            }

            $supplier->scores()->updateOrCreate(
                ['criterion_id' => (int) $criterionId],
                ['value' => (float) $value]
            );
        }
    }

    protected function snapshot(Supplier $supplier): array
    {
        return array_merge(ActivityLogger::snapshot($supplier), [
            'scores' => $supplier->scores()->pluck('value', 'criterion_id')
                ->map(fn ($value) => (float) $value)->all(),
        ]);
    }
}
