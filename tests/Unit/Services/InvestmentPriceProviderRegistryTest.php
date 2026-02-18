<?php

namespace Tests\Unit\Services;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;
use App\Services\InvestmentPriceProviderRegistry;
use Mockery;
use Tests\TestCase;

class InvestmentPriceProviderRegistryTest extends TestCase
{
    public function test_register_and_get_provider(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);

        $registry->register('test_provider', $mockProvider);

        $this->assertSame($mockProvider, $registry->get('test_provider'));
    }

    public function test_has_returns_true_for_registered_provider(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);
        $registry->register('test_provider', $mockProvider);

        $this->assertTrue($registry->has('test_provider'));
    }

    public function test_has_returns_false_for_unregistered_provider(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $this->assertFalse($registry->has('nonexistent'));
    }

    public function test_get_throws_exception_for_unknown_provider(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Unknown price provider: nonexistent');

        $registry->get('nonexistent');
    }

    public function test_get_metadata_returns_provider_metadata(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);
        $mockProvider->shouldReceive('getName')->andReturn('test_provider');
        $mockProvider->shouldReceive('getDisplayName')->andReturn('Test Provider');
        $mockProvider->shouldReceive('supportsRefill')->andReturn(true);
        $mockProvider->shouldReceive('getDescription')->andReturn('Test description');
        $mockProvider->shouldReceive('getInstructions')->andReturn('Test instructions');

        $registry->register('test_provider', $mockProvider);

        $metadata = $registry->getMetadata('test_provider');

        $this->assertEquals([
            'name' => 'test_provider',
            'displayName' => 'Test Provider',
            'refillAvailable' => true,
            'description' => 'Test description',
            'instructions' => 'Test instructions',
        ], $metadata);
    }

    public function test_get_metadata_throws_exception_for_unknown_provider(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Unknown price provider: nonexistent');

        $registry->getMetadata('nonexistent');
    }

    public function test_get_all_metadata_returns_all_providers(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $mockProvider1 = Mockery::mock(InvestmentPriceProvider::class);
        $mockProvider1->shouldReceive('getName')->andReturn('provider1');
        $mockProvider1->shouldReceive('getDisplayName')->andReturn('Provider 1');
        $mockProvider1->shouldReceive('supportsRefill')->andReturn(true);
        $mockProvider1->shouldReceive('getDescription')->andReturn('Description 1');
        $mockProvider1->shouldReceive('getInstructions')->andReturn('Instructions 1');

        $mockProvider2 = Mockery::mock(InvestmentPriceProvider::class);
        $mockProvider2->shouldReceive('getName')->andReturn('provider2');
        $mockProvider2->shouldReceive('getDisplayName')->andReturn('Provider 2');
        $mockProvider2->shouldReceive('supportsRefill')->andReturn(false);
        $mockProvider2->shouldReceive('getDescription')->andReturn('Description 2');
        $mockProvider2->shouldReceive('getInstructions')->andReturn('Instructions 2');

        $registry->register('provider1', $mockProvider1);
        $registry->register('provider2', $mockProvider2);

        $allMetadata = $registry->getAllMetadata();

        $this->assertCount(2, $allMetadata);
        $this->assertEquals([
            'provider1' => [
                'name' => 'provider1',
                'displayName' => 'Provider 1',
                'refillAvailable' => true,
                'description' => 'Description 1',
                'instructions' => 'Instructions 1',
            ],
            'provider2' => [
                'name' => 'provider2',
                'displayName' => 'Provider 2',
                'refillAvailable' => false,
                'description' => 'Description 2',
                'instructions' => 'Instructions 2',
            ],
        ], $allMetadata);
    }

    public function test_get_all_metadata_returns_empty_array_when_no_providers(): void
    {
        $registry = new InvestmentPriceProviderRegistry();

        $this->assertEquals([], $registry->getAllMetadata());
    }
}
