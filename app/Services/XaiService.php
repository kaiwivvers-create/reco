<?php

namespace App\Services;

/**
 * Explainable AI layer.
 *
 * Every recommendation is decomposed into per-criterion contributions
 * (weight x normalised performance), then translated into natural language,
 * a counterfactual ("what would the runner-up need to change?") and a
 * weight-sensitivity check ("is the ranking stable?").
 *
 * All explanations are computed deterministically from the MCDM model, so the
 * system never invents numbers that are not present in the decision matrix.
 */
class XaiService
{
    public function __construct(protected McdmService $mcdm)
    {
    }

    public function explain(array $evaluation): array
    {
        $criteria = $evaluation['criteria'];
        $matrix = $evaluation['matrix'];
        $ranking = $evaluation['ranking'];
        $method = $evaluation['method'];

        $criteriaById = collect($criteria)->keyBy('id');
        $methodLabel = $method === 'topsis' ? 'TOPSIS' : 'SAW';
        $aggregation = $method === 'saw'
            ? 'skor akhir dihitung dari penjumlahan bobot x nilai ternormalisasi (SAW)'
            : 'skor akhir dihitung dari kedekatan terhadap solusi ideal (TOPSIS). Untuk TOPSIS, kontribusi per kriteria menggambarkan posisi performa dalam rentang solusi terburuk-terbaik, bukan penjumlahan aditif yang secara langsung membentuk closeness';

        $winner = $ranking[0] ?? null;

        if (! $winner) {
            return [
                'method_note' => 'Belum ada supplier untuk dijelaskan.',
                'winner' => null,
                'suppliers' => [],
                'comparison' => null,
            ];
        }

        $runner = $ranking[1] ?? null;
        $supplierExplanations = [];

        foreach ($ranking as $row) {
            $sid = $row['supplier_id'];
            $contributions = [];

            foreach ($criteria as $criterion) {
                $cid = $criterion['id'];
                $cell = $matrix[$sid][$cid] ?? null;
                if (! $cell) {
                    continue;
                }

                $w = (float) $criterion['weight_normalized'];
                $ratio = $this->performanceRatio($method, $cell);
                $contributions[] = [
                    'criterion_id' => $cid,
                    'name' => $criterion['name'],
                    'type' => $criterion['type'],
                    'unit' => $criterion['unit'],
                    'weight' => $w,
                    'weight_pct' => $w * 100,
                    'raw' => $cell['raw'],
                    'normalized' => $cell['normalized'],
                    'ratio' => $ratio,
                    'contribution' => $w * $ratio,
                    'raw_text' => $this->rawText($cell['raw'], $criterion),
                    'performance_pct' => $ratio * 100,
                ];
            }

            $totalContribution = array_sum(array_column($contributions, 'contribution'));

            foreach ($contributions as &$contribution) {
                $contribution['share_pct'] = $totalContribution > 0
                    ? ($contribution['contribution'] / $totalContribution) * 100
                    : 0;
                $contribution['points'] = $contribution['contribution'] * 100;
            }
            unset($contribution);

            $strengths = $contributions;
            usort($strengths, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);
            $strengths = array_slice($strengths, 0, 3);

            $weaknesses = array_map(function ($c) {
                $c['lost_potential'] = $c['weight'] * (1 - $c['ratio']) * 100;

                return $c;
            }, $contributions);
            usort($weaknesses, fn ($a, $b) => $b['lost_potential'] <=> $a['lost_potential']);
            $weaknesses = array_values(array_filter(
                array_slice($weaknesses, 0, 2),
                fn ($c) => $c['lost_potential'] > 0.005
            ));

            $top = array_slice($strengths, 0, 2);
            $reasonParts = array_map(
                fn ($c) => $c['name'].' '.$c['raw_text'].' (skor performa '.$this->num($c['performance_pct'], 1).'%)',
                $top
            );

            $narrative = $row['name'].' berada di peringkat '.$row['rank'].' dengan skor '.$this->num($row['score']).' dari 100. ';
            if ($top) {
                $narrative .= 'Kekuatan utamanya adalah '.$this->joinIndonesian(array_column($top, 'name'));
                $narrative .= '; '.$this->joinIndonesian($reasonParts, ' dan ').' menjadi penyumbang terbesar. ';
            }
            if ($weaknesses) {
                $narrative .= 'Faktor yang masih menahan skornya adalah '.$weaknesses[0]['name'].' '.$weaknesses[0]['raw_text'].'.';
            } else {
                $narrative .= 'Tidak ada kelemahan berarti pada seluruh kriteria.';
            }

            $supplierExplanations[$sid] = [
                'supplier_id' => $sid,
                'name' => $row['name'],
                'rank' => $row['rank'],
                'score' => $row['score'],
                'narrative' => trim($narrative),
                'contributions' => $contributions,
                'strengths' => array_map(fn ($c) => [
                    'criterion_id' => $c['criterion_id'],
                    'name' => $c['name'],
                    'text' => $c['name'].' '.$c['raw_text'].' menyumbang '.$this->num($c['points']).' poin ('
                        .$this->num($c['share_pct'], 1).'% dari skor akhir).',
                    'contribution' => $c['contribution'],
                    'share_pct' => $c['share_pct'],
                ], $strengths),
                'weaknesses' => array_map(fn ($c) => [
                    'criterion_id' => $c['criterion_id'],
                    'name' => $c['name'],
                    'text' => $c['name'].' '.$c['raw_text'].' masih menyisakan potensi hingga '
                        .$this->num($c['lost_potential']).' poin bila ditingkatkan.',
                    'lost_potential' => $c['lost_potential'],
                ], $weaknesses),
            ];
        }

        $comparison = $this->buildComparison($evaluation, $winner, $runner, $criteriaById);
        $winnerExplanation = $supplierExplanations[$winner['supplier_id']];

        $summary = $this->buildSummary($winner, $runner, $winnerExplanation, $comparison);

        return [
            'method_note' => 'Penjelasan dihasilkan dari dekomposisi kontribusi tiap kriteria: '.$aggregation
                .'. Angka kontribusi dihitung ulang dari matriks keputusan yang sama, bukan perkiraan.',
            'method_label' => $methodLabel,
            'winner' => array_merge($winnerExplanation, [
                'summary' => $summary['summary'],
                'reasons' => $summary['reasons'],
            ]),
            'suppliers' => $supplierExplanations,
            'comparison' => $comparison,
            'ranking' => $ranking,
        ];
    }

    protected function buildSummary(array $winner, ?array $runner, array $explanation, array $comparison): array
    {
        $strengths = $explanation['strengths'];
        $topNames = array_slice(array_column($strengths, 'name'), 0, 2);

        $summary = $winner['name'].' direkomendasikan sebagai supplier terbaik dengan skor akhir '
            .$this->num($winner['score']).' dari 100.';

        if ($topNames) {
            $summary .= ' Keunggulan utamanya berasal dari '.$this->joinIndonesian($topNames).'.';
        }

        if ($runner) {
            $summary .= ' Selisih '.$this->num($comparison['margin']).' poin dari '.$runner['name']
                .' di peringkat kedua.';
        }

        $reasons = [];
        $reasons[] = 'Skor tertinggi: '.$this->num($winner['score']).' dari 100 pada metode '
            .strtoupper($comparison['method']).', dihitung dari seluruh kriteria berbobot.';

        if ($strengths) {
            $reasons[] = 'Kontribusi terbesar: '.$strengths[0]['text'];
        }

        if (! empty($explanation['weaknesses'])) {
            $reasons[] = 'Catatan perbaikan: '.$explanation['weaknesses'][0]['text'];
        } elseif ($strengths) {
            $reasons[] = 'Tidak ada kriteria yang tertinggal secara signifikan; performa relatif merata.';
        }

        if ($comparison['won']) {
            $reasons[] = 'Dibandingkan peringkat kedua, unggul pada '.$this->joinIndonesian($comparison['won']).'.';
        }
        if ($comparison['lost']) {
            $reasons[] = 'Namun tertinggal pada '.$this->joinIndonesian($comparison['lost'])
                .', sehingga keunggulan tersebut perlu dikonfirmasi dengan kebutuhan pengadaan.';
        }

        if (($comparison['counterfactual']['text'] ?? null) && $runner) {
            $reasons[] = $comparison['counterfactual']['text'];
        }

        return ['summary' => $summary, 'reasons' => $reasons];
    }

    protected function buildComparison(array $evaluation, array $winner, ?array $runner, $criteriaById): array
    {
        $method = $evaluation['method'];
        $matrix = $evaluation['matrix'];
        $margin = $runner ? $winner['score'] - $runner['score'] : 0;

        $rows = [];
        $won = [];
        $lost = [];
        $tied = [];

        if ($runner) {
            foreach ($criteriaById as $cid => $criterion) {
                $winnerCell = $matrix[$winner['supplier_id']][$cid] ?? null;
                $runnerCell = $matrix[$runner['supplier_id']][$cid] ?? null;
                if (! $winnerCell || ! $runnerCell) {
                    continue;
                }

                $winnerValue = $winnerCell['raw'];
                $runnerValue = $runnerCell['raw'];
                $better = $this->compareValues($winnerValue, $runnerValue, $criterion['type']);

                if ($better === 1) {
                    $status = 'unggul';
                    $won[] = $criterion['name'];
                } elseif ($better === -1) {
                    $status = 'tertinggal';
                    $lost[] = $criterion['name'];
                } else {
                    $status = 'setara';
                    $tied[] = $criterion['name'];
                }

                $rows[] = [
                    'criterion_id' => $cid,
                    'name' => $criterion['name'],
                    'type' => $criterion['type'],
                    'unit' => $criterion['unit'],
                    'winner_raw' => $winnerValue,
                    'winner_text' => $this->rawText($winnerValue, $criterion),
                    'runner_raw' => $runnerValue,
                    'runner_text' => $this->rawText($runnerValue, $criterion),
                    'status' => $status,
                    'winner_performance' => $this->performanceRatio($method, $winnerCell) * 100,
                    'runner_performance' => $this->performanceRatio($method, $runnerCell) * 100,
                ];
            }
        }

        $counterfactual = $runner ? $this->counterfactual($evaluation, $winner, $runner) : [
            'text' => 'Hanya ada satu supplier pada evaluasi ini, sehingga tidak ada pembanding untuk analisis kontrafaktual.',
            'possible' => false,
        ];

        $sensitivity = $this->sensitivity($evaluation, $winner);

        return [
            'method' => $method,
            'margin' => $margin,
            'rows' => $rows,
            'won' => $won,
            'lost' => $lost,
            'tied' => $tied,
            'counterfactual' => $counterfactual,
            'sensitivity' => $sensitivity,
        ];
    }

    /**
     * Find the smallest single-criterion change that would let the runner-up
     * match the winner's score. Solved numerically with binary search.
     */
    protected function counterfactual(array $evaluation, array $winner, array $runner): array
    {
        $criteria = $evaluation['criteria'];
        $method = $evaluation['method'];
        $names = collect($evaluation['ranking'])->mapWithKeys(fn ($r) => [$r['supplier_id'] => $r['name']])->all();
        $raw = $this->rawMatrix($evaluation);
        $target = $winner['score'] + 0.0001;
        $best = null;

        foreach ($criteria as $criterion) {
            $cid = $criterion['id'];
            $current = (float) ($raw[$runner['supplier_id']][$cid] ?? 0);
            $isCost = $criterion['type'] === 'cost';
            $columnMax = max(array_map(fn ($sid) => (float) ($raw[$sid][$cid] ?? 0), array_keys($raw)) ?: [1]);

            $scoreWith = function (float $value) use ($raw, $runner, $cid, $criteria, $names, $method) {
                $modified = $raw;
                $modified[$runner['supplier_id']][$cid] = $value;
                $result = $this->mcdm->evaluate($criteria, $modified, $names, $method);

                return (float) ($result['scores'][$runner['supplier_id']] ?? 0);
            };

            if ($isCost) {
                $good = max($current * 0.05, 0.0001);
                if ($current <= 0 || $scoreWith($good) < $target) {
                    continue;
                }
                $bad = $current;
            } else {
                $good = max($current * 5, $columnMax * 2, $current + 1, 1);
                if ($scoreWith($good) < $target) {
                    continue;
                }
                $bad = $current;
            }

            for ($i = 0; $i < 50; $i++) {
                $mid = ($good + $bad) / 2;
                $score = $scoreWith($mid);
                if ($score >= $target) {
                    if ($isCost) {
                        $good = $mid;
                    } else {
                        $good = $mid;
                    }
                } else {
                    $bad = $mid;
                }
            }

            $needed = $good;
            $changePct = abs($needed - $current) / max(abs($current), 0.0001) * 100;
            $candidate = [
                'possible' => true,
                'criterion' => $criterion['name'],
                'criterion_id' => $cid,
                'direction' => $isCost ? 'menurunkan' : 'meningkatkan',
                'from' => $current,
                'to' => $needed,
                'from_text' => $this->rawText($current, $criterion),
                'to_text' => $this->rawText($needed, $criterion),
                'change_pct' => $changePct,
                'runner' => $runner['name'],
                'winner' => $winner['name'],
            ];

            if ($best === null || $changePct < $best['change_pct']) {
                $best = $candidate;
            }
        }

        if (! $best) {
            return [
                'possible' => false,
                'text' => 'Tidak ada perubahan satu kriteria saja yang cukup untuk menyamai skor '.$winner['name']
                    .' tanpa mengubah kriteria lain; keunggulannya tersebar di beberapa faktor.',
            ];
        }

        $best['text'] = 'Analisis kontrafaktual: jika '.$best['runner'].' '.$best['direction'].' '.$best['criterion']
            .' dari '.$best['from_text'].' menjadi '.$best['to_text'].' (perubahan '
            .$this->num($best['change_pct'], 1).'%), skornya akan menyamai '.$best['winner']
            .'. Ini menunjukkan seberapa dekat selisih keduanya pada faktor tersebut.';

        return $best;
    }

    /**
     * Perturb each weight by +/-20% to test whether the recommendation is stable.
     */
    protected function sensitivity(array $evaluation, array $winner): array
    {
        $criteria = $evaluation['criteria'];
        $raw = $this->rawMatrix($evaluation);
        $names = collect($evaluation['ranking'])->mapWithKeys(fn ($r) => [$r['supplier_id'] => $r['name']])->all();
        $scenarios = [];

        if (count($evaluation['ranking']) < 2) {
            return [
                'total' => 0,
                'stable' => 0,
                'text' => 'Analisis sensitivitas membutuhkan minimal dua supplier.',
                'scenarios' => [],
            ];
        }

        foreach ($criteria as $criterion) {
            foreach ([20, -20] as $percent) {
                $adjusted = $criteria;
                foreach ($adjusted as &$row) {
                    if ($row['id'] === $criterion['id']) {
                        $row['weight'] = max(0.01, $row['weight'] * (1 + $percent / 100));
                    }
                }
                unset($row);

                $result = $this->mcdm->evaluate($adjusted, $raw, $names, $evaluation['method']);
                $newWinner = $result['ranking'][0] ?? null;
                $stable = $newWinner && $newWinner['supplier_id'] === $winner['supplier_id'];

                $scenarios[] = [
                    'criterion' => $criterion['name'],
                    'percent' => $percent,
                    'winner' => $newWinner['name'] ?? '-',
                    'stable' => $stable,
                ];
            }
        }

        $total = count($scenarios);
        $stable = count(array_filter($scenarios, fn ($s) => $s['stable']));
        $unstable = array_values(array_filter($scenarios, fn ($s) => ! $s['stable']));

        if ($stable === $total) {
            $text = 'Rekomendasi stabil: pada seluruh '.$total.' skenario perubahan bobot kriteria sebesar ±20%, '
                .$winner['name'].' tetap berada di peringkat pertama.';
        } else {
            $sensitiveCriteria = array_values(array_unique(array_column($unstable, 'criterion')));
            $text = 'Rekomendasi cukup sensitif: peringkat pertama berubah pada '.($total - $stable).' dari '.$total
                .' skenario. Kriteria yang paling memengaruhi adalah '.$this->joinIndonesian($sensitiveCriteria)
                .', sehingga bobot kriteria tersebut sebaiknya ditinjau kembali.';
        }

        return [
            'total' => $total,
            'stable' => $stable,
            'text' => $text,
            'scenarios' => $scenarios,
        ];
    }

    protected function performanceRatio(string $method, array $cell): float
    {
        if ($method === 'topsis' && ! empty($cell['ideal'])) {
            $best = $cell['ideal']['best'];
            $worst = $cell['ideal']['worst'];
            $range = $best - $worst;

            return $range == 0 ? 1.0 : max(0.0, min(1.0, ($cell['weighted'] - $worst) / $range));
        }

        return max(0.0, min(1.0, (float) $cell['normalized']));
    }

    protected function rawMatrix(array $evaluation): array
    {
        $raw = [];
        foreach ($evaluation['matrix'] as $sid => $cells) {
            foreach ($cells as $cid => $cell) {
                $raw[$sid][$cid] = $cell['raw'];
            }
        }

        return $raw;
    }

    protected function compareValues(float $a, float $b, string $type): int
    {
        if (abs($a - $b) < 0.0000001) {
            return 0;
        }

        if ($type === 'cost') {
            return $a < $b ? 1 : -1;
        }

        return $a > $b ? 1 : -1;
    }

    protected function rawText(float $value, array $criterion): string
    {
        $text = $this->num($value);
        $unit = trim((string) ($criterion['unit'] ?? ''));

        if ($unit === '') {
            return $text;
        }

        return $unit === 'Rp' ? 'Rp '.$text : $text.' '.$unit;
    }

    protected function num(float $value, int $decimals = 2): string
    {
        if ($decimals === 2 && abs($value - round($value)) < 0.000001) {
            return number_format($value, 0, ',', '.');
        }

        return number_format($value, $decimals, ',', '.');
    }

    protected function joinIndonesian(array $items, string $lastSeparator = ' dan '): string
    {
        $items = array_values(array_filter($items));

        if (count($items) <= 1) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).$lastSeparator.$last;
    }
}
