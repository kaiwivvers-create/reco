<?php

namespace App\Services;

/**
 * Multi-Criteria Decision Making engine.
 *
 * Supports SAW (Simple Additive Weighting) and TOPSIS.
 * Expected input:
 *   $criteria = [['id'=>1,'code'=>'C1','name'=>'Harga','type'=>'cost','weight'=>30,'unit'=>'Rp'], ...]
 *   $matrix   = [supplierId => [criterionId => rawValue], ...]
 */
class McdmService
{
    public const METHODS = [
        'saw' => 'SAW — Simple Additive Weighting',
        'topsis' => 'TOPSIS — Technique for Order Preference by Similarity to Ideal Solution',
    ];

    public function evaluate(array $criteria, array $matrix, array $suppliers, string $method = 'saw'): array
    {
        $method = array_key_exists($method, self::METHODS) ? $method : 'saw';
        $criteria = $this->prepareCriteria($criteria);
        $supplierIds = array_keys($suppliers);

        $normalized = [];
        $weighted = [];
        $raw = [];

        foreach ($supplierIds as $sid) {
            $normalized[$sid] = [];
            $weighted[$sid] = [];
            $raw[$sid] = [];
        }

        $ideal = [];

        if ($method === 'topsis') {
            $denominators = [];
            foreach ($criteria as $criterion) {
                $cid = $criterion['id'];
                $sumSquares = 0.0;
                foreach ($supplierIds as $sid) {
                    $value = (float) ($matrix[$sid][$cid] ?? 0);
                    $raw[$sid][$cid] = $value;
                    $sumSquares += $value * $value;
                }
                $denominators[$cid] = sqrt($sumSquares);
            }

            foreach ($criteria as $criterion) {
                $cid = $criterion['id'];
                $w = $criterion['weight_normalized'];
                foreach ($supplierIds as $sid) {
                    $denom = $denominators[$cid];
                    $r = $denom > 0 ? $raw[$sid][$cid] / $denom : 0.0;
                    $normalized[$sid][$cid] = $r;
                    $weighted[$sid][$cid] = $w * $r;
                }

                $column = array_map(fn ($sid) => $weighted[$sid][$cid], $supplierIds);
                $max = max($column);
                $min = min($column);
                $ideal[$cid] = $criterion['type'] === 'cost'
                    ? ['best' => $min, 'worst' => $max]
                    : ['best' => $max, 'worst' => $min];
            }
        } else {
            foreach ($criteria as $criterion) {
                $cid = $criterion['id'];
                $w = $criterion['weight_normalized'];
                $column = [];
                foreach ($supplierIds as $sid) {
                    $column[$sid] = (float) ($matrix[$sid][$cid] ?? 0);
                    $raw[$sid][$cid] = $column[$sid];
                }
                $max = max($column);
                $min = min($column);

                foreach ($supplierIds as $sid) {
                    $value = $column[$sid];

                    if ($max == $min) {
                        $r = 1.0;
                    } elseif ($criterion['type'] === 'cost') {
                        $r = $value <= 0 ? 1.0 : $min / $value;
                    } else {
                        $r = $max > 0 ? $value / $max : 0.0;
                    }

                    $normalized[$sid][$cid] = $r;
                    $weighted[$sid][$cid] = $w * $r;
                }
            }
        }

        $scores = [];
        $distances = [];

        foreach ($supplierIds as $sid) {
            if ($method === 'topsis') {
                $dPlus = 0.0;
                $dMinus = 0.0;
                foreach ($criteria as $criterion) {
                    $cid = $criterion['id'];
                    $v = $weighted[$sid][$cid];
                    $dPlus += ($v - $ideal[$cid]['best']) ** 2;
                    $dMinus += ($v - $ideal[$cid]['worst']) ** 2;
                }
                $dPlus = sqrt($dPlus);
                $dMinus = sqrt($dMinus);
                $closeness = ($dPlus + $dMinus) > 0 ? $dMinus / ($dPlus + $dMinus) : 0.0;
                $distances[$sid] = ['d_plus' => $dPlus, 'd_minus' => $dMinus, 'closeness' => $closeness];
                $scores[$sid] = $closeness * 100;
            } else {
                $scores[$sid] = array_sum($weighted[$sid]) * 100;
            }
        }

        $ranking = [];
        foreach ($supplierIds as $sid) {
            $supplierName = $suppliers[$sid]['name'] ?? $suppliers[$sid] ?? null;
            if (is_array($supplierName)) {
                $supplierName = null;
            }

            $ranking[] = [
                'supplier_id' => $sid,
                'name' => $supplierName ?: ('Supplier #'.$sid),
                'score' => round($scores[$sid], 4),
                'd_plus' => $distances[$sid]['d_plus'] ?? null,
                'd_minus' => $distances[$sid]['d_minus'] ?? null,
                'closeness' => $distances[$sid]['closeness'] ?? null,
            ];
        }

        usort($ranking, function ($a, $b) {
            if ($a['score'] === $b['score']) {
                return strcmp($a['name'], $b['name']);
            }

            return $b['score'] <=> $a['score'];
        });

        foreach ($ranking as $index => &$row) {
            $row['rank'] = $index + 1;
        }
        unset($row);

        $matrixDetail = [];
        foreach ($supplierIds as $sid) {
            foreach ($criteria as $criterion) {
                $cid = $criterion['id'];
                $matrixDetail[$sid][$cid] = [
                    'raw' => $raw[$sid][$cid],
                    'normalized' => $normalized[$sid][$cid],
                    'weighted' => $weighted[$sid][$cid],
                    'ideal' => $ideal[$cid] ?? null,
                ];
            }
        }

        return [
            'method' => $method,
            'criteria' => $criteria,
            'matrix' => $matrixDetail,
            'ideal' => $ideal,
            'scores' => $scores,
            'ranking' => $ranking,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * Normalise raw criteria definitions into a uniform shape.
     */
    protected function prepareCriteria(array $criteria): array
    {
        $criteria = collect($criteria)->map(function ($criterion) {
            return [
                'id' => (int) $criterion['id'],
                'code' => $criterion['code'] ?? '',
                'name' => $criterion['name'] ?? '',
                'type' => ($criterion['type'] ?? 'benefit') === 'cost' ? 'cost' : 'benefit',
                'weight' => (float) ($criterion['weight'] ?? 0),
                'unit' => $criterion['unit'] ?? '',
            ];
        })->values()->all();

        $total = array_sum(array_column($criteria, 'weight'));

        foreach ($criteria as &$criterion) {
            $criterion['weight_normalized'] = $total > 0
                ? $criterion['weight'] / $total
                : (count($criteria) > 0 ? 1 / count($criteria) : 0);
        }
        unset($criterion);

        return $criteria;
    }

    /**
     * Turn an evaluation snapshot into the raw criteria array expected above.
     */
    public static function criteriaFromSnapshot(array $snapshot): array
    {
        return collect($snapshot)->map(fn ($c) => [
            'id' => (int) $c['id'],
            'code' => $c['code'] ?? '',
            'name' => $c['name'],
            'type' => $c['type'] ?? 'benefit',
            'weight' => (float) ($c['weight'] ?? 0),
            'unit' => $c['unit'] ?? '',
        ])->values()->all();
    }
}
