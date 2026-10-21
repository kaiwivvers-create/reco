<?php

namespace Tests\Unit;

use App\Services\McdmService;
use App\Services\XaiService;
use PHPUnit\Framework\TestCase;

class XaiServiceTest extends TestCase
{
    protected function evaluation(): array
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
        $names = [1 => ['name' => 'Supplier A'], 2 => ['name' => 'Supplier B'], 3 => ['name' => 'Supplier C']];

        $result = (new McdmService())->evaluate($criteria, $matrix, $names, 'saw');

        return (new XaiService(new McdmService()))->explain($result);
    }

    public function test_it_explains_the_winner_with_reasons_and_contributions(): void
    {
        $xai = $this->evaluation();

        $this->assertSame('Supplier B', $xai['winner']['name']);
        $this->assertNotEmpty($xai['winner']['summary']);
        $this->assertGreaterThanOrEqual(4, count($xai['winner']['reasons']));
        $this->assertCount(4, $xai['winner']['contributions']);
        $this->assertNotEmpty($xai['winner']['strengths']);
        $this->assertNotEmpty($xai['winner']['weaknesses']);
    }

    public function test_contribution_shares_sum_to_one_hundred(): void
    {
        $xai = $this->evaluation();
        $totalShare = array_sum(array_column($xai['winner']['contributions'], 'share_pct'));

        $this->assertEqualsWithDelta(100, $totalShare, 0.01);
    }

    public function test_it_compares_winner_with_runner_up(): void
    {
        $xai = $this->evaluation();
        $comparison = $xai['comparison'];

        $this->assertGreaterThan(0, $comparison['margin']);
        $this->assertCount(4, $comparison['rows']);
        $this->assertContains('Harga', $comparison['won']);
        $this->assertContains('Kualitas', $comparison['lost']);
    }

    public function test_it_generates_counterfactual_and_sensitivity_analysis(): void
    {
        $xai = $this->evaluation();
        $comparison = $xai['comparison'];

        $this->assertTrue($comparison['counterfactual']['possible']);
        $this->assertStringContainsString('jika', $comparison['counterfactual']['text']);
        $this->assertSame(8, $comparison['sensitivity']['total']);
        $this->assertSame(8, $comparison['sensitivity']['stable']);
    }

    public function test_every_supplier_receives_a_narrative(): void
    {
        $xai = $this->evaluation();

        $this->assertCount(3, $xai['suppliers']);
        foreach ($xai['suppliers'] as $supplier) {
            $this->assertNotEmpty($supplier['narrative']);
            $this->assertNotEmpty($supplier['contributions']);
        }
    }
}
