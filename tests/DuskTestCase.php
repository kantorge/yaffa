<?php

namespace Tests;

use AleBatistella\DuskApiConf\Traits\UsesDuskApiConfig;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use Tests\Browser\DuskMacros;

abstract class DuskTestCase extends BaseTestCase
{
    use CreatesApplication;
    use UsesDuskApiConfig;

    // Define the user email, that is generally used for testing
    // This should match the primary user email in the database\seeders\DatabaseSeeder.php file
    protected const string USER_EMAIL = 'demo@yaffa.cc';

    /**
     * Prepare for Dusk test execution.
     */
    public static function prepare(): void
    {
        if (!static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DuskMacros::register();
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return RemoteWebDriver
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions())->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
        ])->unless($this->hasHeadlessDisabled(), fn($items) => $items->merge([
                '--disable-gpu',
                '--headless=new',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--ignore-certificate-errors',
                '--allow-insecure-localhost',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-sync',
                '--disable-translate',
                '--disable-search-engine-choice-screen',
                '--disable-smooth-scrolling',
            ]))->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY,
                $options
            )
        );
    }

    /**
     * Determine whether the Dusk command has disabled headless mode.
     *
     * @return bool
     */
    protected function hasHeadlessDisabled(): bool
    {
        return isset($_SERVER['DUSK_HEADLESS_DISABLED']) ||
            isset($_ENV['DUSK_HEADLESS_DISABLED']);
    }

    /**
     * Determine if the browser window should start maximized.
     *
     * @return bool
     */
    protected function shouldStartMaximized(): bool
    {
        return isset($_SERVER['DUSK_START_MAXIMIZED']) ||
            isset($_ENV['DUSK_START_MAXIMIZED']);
    }

    /**
     * Helper function to read the number of rows in a DataTable
     *
     * @param Browser $browser
     * @param string $tableSelector
     * @return int
     */
    protected function getTableRowCount(Browser $browser, string $tableSelector): int
    {
        return $browser->script("return $('{$tableSelector}').DataTable().rows({search:'applied'}).count()")[0];
    }
}
