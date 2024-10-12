<?php

namespace Tests\Unit\Other;

use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;
use App\Listeners\CreateDefaultAssetsForNewUser;

class DefaultAssetNameTranslationsExistTest extends TestCase
{
    public function testAllStringsAreAvailableInDefaultAssets()
    {
        // Load the default assets array
        $defaultAssets = include base_path('resources/lang/en/default_assets.php');

        // Create an instance of the listener
        $listener = new CreateDefaultAssetsForNewUser();

        // Use reflection to access private properties
        $reflection = new ReflectionClass($listener);
        $properties = array_filter(
            $reflection->getProperties(ReflectionProperty::IS_PRIVATE),
            fn($property) => str_starts_with($property->getName(), 'config') && is_array($property->getValue($listener))
        );

        foreach ($properties as $property) {
            // Recursively check all strings in the config
            $this->checkStringsInConfig(
                $property->getValue($listener),
                $defaultAssets
            );
        }
    }

    private function checkStringsInConfig(array $config, array $defaultAssets): void
    {
        foreach ($config as $value) {
            if (is_array($value)) {
                $this->checkStringsInConfig($value, $defaultAssets);
            } elseif (is_string($value)) {
                $translatedString = __($value);
                $this->assertNotEquals(
                    $translatedString,
                    $value,
                    "String '{$value}' is not available in default_assets.php"
                );
            } else {
                $this->fail('Unexpected value in config');
            }
        }
    }
}
