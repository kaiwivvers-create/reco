<?php

namespace Tests\Unit;

use App\Services\McdmService;
use PHPUnit\Framework\TestCase;

class McdmServiceTest extends TestCase
{
    protected function sample(): array
    {
        $criteria = [
            ['id' => 1, 'code' => 'C01', 'name' => 'Harga', 'type' => 'cost', 'weight' => 30, 'unit' => 'Rp'],
            ['id' => 2, 'code' => 'C02', 'name' => 'Kualitas', 'type' => 'benefit', 'weight' => 30, 'unit' => 'skor'],
            ['id' => 3, 'code' => 'C03', 'name' => 'Pengiriman', 'type' => 'cost', 'weight' => 25, 'unit' => 'hari'],
            ['id' => 4, 'code' => 'C04', 'name' => 'Keandalan', 'type' => 'benefit', 'weight' => 15, 'unit' => 'skor'],
        ];

        $matrix = [
            1 => [1 => 15000000, 2 => 5, 3 => 7, 4 => 5],
            2 => [1 => 12000000, 2 => 4, 3 => 3, 4 => 3],
            3 => [1 => 18000000, 2 => 3, 3 => 4, 4 => 5],
        ];

        $names = [
            1 => ['name' => 'Supplier A'],
            2 => ['name' => 'Supplier B'],
            3 => ['name' => 'Supplier C'],
        ];

        return [$criteria, $matrix, $names];
    }

    public function test_saw_produces_expected_scores_and_ranking(): void
    {
        [$criteria, $matrix, $names] = $this->sample();
        $result = (new McdmService())->evaluate($criteria, $matrix, $names, 'saw');

        $scores = $result['scores'];
        $this->assertEqualsWithDelta(79.7143, $scores[1], 0.001);
        $this->assertEqualsWithDelta(88.0, $scores[2], 0.001);
        $this->assertEqualsWithDelta(71.75, $scores[3], 0.001);

        $this->assertSame('Supplier B', $result['ranking'][0]['name']);
        $this->assertSame(1, $result['ranking'][0]['rank']);
        $this->assertSame('Supplier C', $result['ranking'][2]['name']);
    }

    public function test_saw_normalises_weights_that_do_not_sum_to_100(): void
    {
        [$criteria, $matrix, $names] = $this->sample();
        foreach ($criteria as &$criterion) {
            $criterion['weight'] = $criterion['weight'] / 100;
        }
        unset($criterion);

        $result = (new McdmService())->evaluate($criteria, $matrix, $names, 'saw');

        $this->assertEqualsWithDelta(88.0, $result['scores'][2], 0.001);
        $this->assertSame(1, $result['ranking'][0]['rank']);
    }

    public function test_topsis_returns_scores_between_zero_and_hundred(): void
    {
        [$criteria, $matrix, $names] = $this->sample();
        $result = (new McdmService())->evaluate($criteria, $matrix, $names, 'topsis');

        foreach ($result['scores'] as $score) {
            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }

        $this->assertSame('topsis', $result['method']);
        $this->assertSame(3, count($result['ranking']));
        $this->assertNotNull($result['ranking'][0]['d_plus']);
        $this->assertSame('Supplier B', $result['ranking'][0]['name']);
    }

    public function test_cost_criterion_prefers_lower_values(): void
    {
        $criteria = [
            ['id' => 1, 'code' => 'C1', 'name' => 'Harga', 'type' => 'cost', 'weight' => 100],
        ];
        $matrix = [1 => [1 => 100], 2 => [1 => 200]];

        $result = (new McdmService())->evaluate($criteria, $matrix, [1 => ['name' => 'Murah'], 2 => ['name' => 'Mahal']], 'saw');

        $this->assertGreaterThan($result['scores'][2], $result['scores'][1]);
        $this->assertSame('Murah', $result['ranking'][0]['name']);
    }

    public function test_benefit_criterion_prefers_higher_values(): void
    {
        $criteria = [
            ['id' => 1, 'code' => 'C1', 'name' => 'Kualitas', 'type' => 'benefit', 'weight' => 100],
        ];
        $matrix = [1 => [1 => 3], 2 => [1 => 5]];

        $result = (new McdmService())->evaluate($criteria, $matrix, [1 => ['name' => 'Biasa'], 2 => ['name' => 'Bagus']], 'saw');

        $this->assertSame('Bagus', $result['ranking'][0]['name']);
    }
}
