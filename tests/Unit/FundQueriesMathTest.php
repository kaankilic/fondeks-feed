<?php

namespace Tests\Unit;

use App\Services\Fondeks\FundQueries;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The pure maths behind the v2.1 fund figures — category percentile and the
 * category median that drives consistency. The DB-backed methods around them
 * lean on Postgres (lateral joins, generate_series), so these cover the parts
 * that stand on their own, invoked directly since they carry no state.
 */
class FundQueriesMathTest extends TestCase
{
    private function call(string $method, array $args): mixed
    {
        $ref = new ReflectionMethod(FundQueries::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke(null, ...$args);
    }

    public function test_percentile_rank_places_best_at_100_and_worst_at_0(): void
    {
        $sorted = [10.0, 20.0, 30.0];

        $this->assertSame(100, $this->call('percentileRank', [$sorted, 30.0]));
        $this->assertSame(50, $this->call('percentileRank', [$sorted, 20.0]));
        $this->assertSame(0, $this->call('percentileRank', [$sorted, 10.0]));
    }

    public function test_percentile_rank_puts_ties_just_under_the_top(): void
    {
        // Two funds share the best return: each stands strictly above only two
        // of the three others, so both land just under 100 (round(2/3·100) = 67)
        // rather than one of them claiming the top outright.
        $sorted = [10.0, 20.0, 30.0, 30.0];

        $this->assertSame(33, $this->call('percentileRank', [$sorted, 20.0]));
        $this->assertSame(67, $this->call('percentileRank', [$sorted, 30.0]));
    }

    public function test_percentile_rank_of_a_lone_fund_is_the_top(): void
    {
        $this->assertSame(100, $this->call('percentileRank', [[42.0], 42.0]));
    }

    public function test_median_of_an_odd_list_is_the_middle_value(): void
    {
        $this->assertSame(2.0, $this->call('median', [[3.0, 1.0, 2.0]]));
    }

    public function test_median_of_an_even_list_averages_the_middle_pair(): void
    {
        $this->assertSame(2.5, $this->call('median', [[4.0, 1.0, 3.0, 2.0]]));
    }

    public function test_median_of_an_empty_list_is_null(): void
    {
        $this->assertNull($this->call('median', [[]]));
    }
}
