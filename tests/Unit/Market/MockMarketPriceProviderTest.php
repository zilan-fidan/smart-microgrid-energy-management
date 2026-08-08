<?php

namespace Tests\Unit\Market;

use App\Services\Market\MockMarketPriceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage: getHourlyPrices() used to redraw a fresh random
 * series on every call, so an hour's price could differ between the
 * moment a decision was made and the moment the baseline calculator (or
 * a later hour re-reading "today's" prices) looked it up. The series must
 * be generated once and memoized for the life of the instance.
 */
class MockMarketPriceProviderTest extends TestCase
{
    public function test_repeated_calls_return_the_identical_series(): void
    {
        $provider = new MockMarketPriceProvider();

        $first = $provider->getHourlyPrices();
        $second = $provider->getHourlyPrices();
        $third = $provider->getHourlyPrices();

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
    }

    public function test_two_separate_instances_are_not_forced_to_match(): void
    {
        // Sanity check that memoization is per-instance, not a shared static —
        // a fresh provider should still be free to draw its own series.
        $a = (new MockMarketPriceProvider())->getHourlyPrices();
        $b = (new MockMarketPriceProvider())->getHourlyPrices();

        $this->assertCount(24, $a);
        $this->assertCount(24, $b);
    }
}
