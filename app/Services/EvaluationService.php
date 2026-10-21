<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationResult;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    public function __construct(
        protected McdmService $mcdm,
        protected XaiService $xai,
    ) {
    }

    /**
     * @param  array<int,array{id:int,name:string,type:string,weight:float,unit?:string,code?:string}>  $criteria
     * @param  Collection<int,Supplier>  $suppliers
     */
    public function compute(array $criteria, Collection $suppliers, string $method): array
    {
        $matrix = [];
        $names = [];

        foreach ($suppliers as $supplier) {
            $names[$supplier->id] = $supplier->name;
            foreach ($criteria as $criterion) {
                $matrix[$supplier->id][$criterion['id']] = (float) ($supplier->scoreFor((int) $criterion['id']) ?? 0);
            }
        }

        $result = $this->mcdm->evaluate($criteria, $matrix, $names, $method);
        $result['xai'] = $this->xai->explain($result);

        return $result;
    }

    /**
     * Return the stored explanation, rebuilding it from the frozen result
     * snapshot when an older evaluation does not have one yet.
     */
    public function explanationFor(Evaluation $evaluation): array
    {
        if (is_array($evaluation->explanation) && $evaluation->explanation !== []) {
            return $evaluation->explanation;
        }

        $evaluation->loadMissing('results');
        $criteria = \App\Services\McdmService::criteriaFromSnapshot($evaluation->criteria_snapshot ?? []);
        $suppliers = $evaluation->results->mapWithKeys(fn ($result) => [
            $result->supplier_id => ['name' => $result->supplier_name],
        ])->all();

        $matrix = [];
        foreach ($evaluation->results as $result) {
            foreach (($result->detail['matrix'] ?? []) as $cid => $cell) {
                $matrix[$result->supplier_id][$cid] = $cell['raw'];
            }
        }

        if (! $criteria || ! $matrix) {
            return [];
        }

        $result = $this->mcdm->evaluate($criteria, $matrix, $suppliers, $evaluation->method);

        return $this->xai->explain($result);
    }

    /**
     * Persist an evaluation with a frozen snapshot of criteria, scores and explanations.
     *
     * @param  array{name:string,method:string,notes?:?string,criteria:array,supplier_ids:array}  $payload
     */
    public function persist(array $payload, ?int $userId = null): Evaluation
    {
        $suppliers = Supplier::with('scores')->whereIn('id', $payload['supplier_ids'])->get();
        $result = $this->compute($payload['criteria'], $suppliers, $payload['method']);

        return DB::transaction(function () use ($payload, $result, $userId) {
            $evaluation = Evaluation::create([
                'name' => $payload['name'],
                'method' => $payload['method'],
                'notes' => $payload['notes'] ?? null,
                'criteria_snapshot' => $payload['criteria'],
                'supplier_ids' => array_values(array_map('intval', $payload['supplier_ids'])),
                'explanation' => $result['xai'],
                'created_by' => $userId,
            ]);

            foreach ($result['ranking'] as $row) {
                $sid = $row['supplier_id'];
                $supplierXai = $result['xai']['suppliers'][$sid] ?? [];

                EvaluationResult::create([
                    'evaluation_id' => $evaluation->id,
                    'supplier_id' => $sid,
                    'supplier_name' => $row['name'],
                    'rank' => $row['rank'],
                    'score' => $row['score'],
                    'detail' => [
                        'matrix' => $result['matrix'][$sid] ?? [],
                        'contributions' => $supplierXai['contributions'] ?? [],
                        'narrative' => $supplierXai['narrative'] ?? '',
                        'd_plus' => $row['d_plus'],
                        'd_minus' => $row['d_minus'],
                        'weights' => collect($result['criteria'])->mapWithKeys(
                            fn ($c) => [$c['id'] => $c['weight_normalized']]
                        )->all(),
                    ],
                ]);
            }

            return $evaluation->load('results');
        });
    }
}
