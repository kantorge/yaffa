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
            fn($property) => strpos($property->getName(), 'config') === 0 && is_array($property->getValue($listener))
        );

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $config = $property->getValue($listener);

            // Recursively check all strings in the config
            $this->checkStringsInConfig($config, $defaultAssets);
        }
    }

    private function checkStringsInConfig(array $config, array $defaultAssets)
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->checkStringsInConfig($value, $defaultAssets);
            } elseif (is_string($value)) {
                $translatedString = __($value);
                $this->assertNotEquals($translatedString, $value, "String '{$value}' is not available in default_assets.php");
            }
        }
    }
}
