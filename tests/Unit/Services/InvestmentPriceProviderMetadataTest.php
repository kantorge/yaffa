<?php

namespace Tests\Unit\Services;

use App\Contracts\InvestmentPriceProvider;
use App\Exceptions\PriceProviderException;
use App\Services\InvestmentPriceProviderMetadata;
use App\Services\InvestmentPriceProviderRegistry;
use Mockery;
use Tests\TestCase;

class InvestmentPriceProviderMetadataTest extends TestCase
{
    public function test_get_returns_provider_metadata(): void
    {
        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);
        $mockProvider->shouldReceive('getName')->andReturn('test_provider');
        $mockProvider->shouldReceive('getDisplayName')->andReturn('Test Provider');
        $mockProvider->shouldReceive('supportsRefill')->andReturn(true);
        $mockProvider->shouldReceive('getDescription')->andReturn('Test description');
        $mockProvider->shouldReceive('getInstructions')->andReturn('Test instructions');

        $registry = $this->app->make(InvestmentPriceProviderRegistry::class);
        $registry->register('test_provider', $mockProvider);

        $metadata = InvestmentPriceProviderMetadata::get('test_provider');

        $this->assertEquals([
            'name' => 'test_provider',
            'displayName' => 'Test Provider',
            'refillAvailable' => true,
            'description' => 'Test description',
            'instructions' => 'Test instructions',
        ], $metadata);
    }

    public function test_all_returns_all_providers(): void
    {
        // Create a fresh registry for this test
        $registry = new InvestmentPriceProviderRegistry();
        $this->app->instance(InvestmentPriceProviderRegistry::class, $registry);

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

        $allMetadata = InvestmentPriceProviderMetadata::all();

        $this->assertCount(2, $allMetadata);
        $this->assertArrayHasKey('provider1', $allMetadata);
        $this->assertArrayHasKey('provider2', $allMetadata);
    }

    public function test_display_name_returns_display_name(): void
    {
        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);
        $mockProvider->shouldReceive('getName')->andReturn('test_provider');
        $mockProvider->shouldReceive('getDisplayName')->andReturn('Test Provider');
        $mockProvider->shouldReceive('supportsRefill')->andReturn(true);
        $mockProvider->shouldReceive('getDescription')->andReturn('Test description');
        $mockProvider->shouldReceive('getInstructions')->andReturn('Test instructions');

        $registry = $this->app->make(InvestmentPriceProviderRegistry::class);
        $registry->register('test_provider', $mockProvider);

        $displayName = InvestmentPriceProviderMetadata::displayName('test_provider');

        $this->assertEquals('Test Provider', $displayName);
    }

    public function test_has_returns_true_for_registered_provider(): void
    {
        $mockProvider = Mockery::mock(InvestmentPriceProvider::class);

        $registry = $this->app->make(InvestmentPriceProviderRegistry::class);
        $registry->register('test_provider', $mockProvider);

        $this->assertTrue(InvestmentPriceProviderMetadata::has('test_provider'));
    }

    public function test_has_returns_false_for_unregistered_provider(): void
    {
        $this->assertFalse(InvestmentPriceProviderMetadata::has('nonexistent'));
    }

    public function test_get_throws_exception_for_unknown_provider(): void
    {
        $this->expectException(PriceProviderException::class);
        $this->expectExceptionMessage('Unknown price provider: nonexistent');

        InvestmentPriceProviderMetadata::get('nonexistent');
    }
}
