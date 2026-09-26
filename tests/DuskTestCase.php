<?php

namespace Tests;

use AleBatistella\DuskApiConf\Traits\UsesDuskApiConfig;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Facades\Storage;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->browse(function ($browser) {
            $browser->resize(1920, 1080);
        });
    }

    /**
     * alebatistella/duskapiconf persists setConfig()/getConfig() overrides to a temp file
     * (storage/app/duskapiconf_tmp.txt by default) that its service provider re-applies to
     * config() on every non-production boot - not just during Dusk runs, but for any artisan
     * command afterward - until the file is removed. The package only removes it via an
     * explicit resetConfig() call, so a test that calls setConfig() and then fails/times out
     * before its own cleanup lines run leaves the override stuck indefinitely, silently
     * corrupting config for the whole dev environment (e.g. yaffa.sandbox_mode stuck `true`).
     * Deleting the file unconditionally here - rather than relying on each test's own
     * try/finally discipline - guarantees it never survives a test, since tearDown() still
     * runs after an assertion failure or exception.
     */
    protected function tearDown(): void
    {
        Storage::disk(config('duskapiconf.storage.disk', 'local'))
            ->delete(config('duskapiconf.storage.file', 'duskapiconf_tmp.txt'));

        parent::tearDown();
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return RemoteWebDriver
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions())->addArguments([
            '--window-size=1920,1080',
            '--force-device-scale-factor=1',
            '--headless=new',
            '--disable-gpu',
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
        ]);

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
     * Helper function to read the number of rows in a DataTable
     *
     * @param Browser $browser
     * @param string $tableSelector
     * @return int
     */
    protected function getTableRowCount(Browser $browser, string $tableSelector): int
    {
        return (int) $browser->script(
            'return $(' . json_encode($tableSelector) . ').DataTable().rows({ search: "applied" }).count();'
        )[0];
    }

    /**
     * @param Browser $browser
     * @param string $selectSelector
     * @return array<int, string>
     */
    protected function getSelect2Values(Browser $browser, string $selectSelector): array
    {
        $selectedValues = $browser->script(
            'return $(' . json_encode($selectSelector) . ').select2("val") ?? [];'
        )[0];

        if (!is_array($selectedValues)) {
            return [];
        }

        return array_map('strval', $selectedValues);
    }

    protected function getSelect2ValueCount(Browser $browser, string $selectSelector): int
    {
        return count($this->getSelect2Values($browser, $selectSelector));
    }

    protected function waitForSelect2ValueCount(
        Browser $browser,
        string $selectSelector,
        int $expectedCount,
        int $seconds = 10,
        int $milliseconds = 100,
    ): void {
        $browser->waitUsing(
            $seconds,
            $milliseconds,
            fn () => $this->getSelect2ValueCount($browser, $selectSelector) === $expectedCount
        );
    }

    /**
     * @param Browser $browser
     * @param string $selectSelector
     * @param array<int, int|string> $expectedValues
     */
    protected function assertSelect2Values(Browser $browser, string $selectSelector, array $expectedValues): void
    {
        $selectedValues = $this->getSelect2Values($browser, $selectSelector);
        $expectedValues = array_map('strval', $expectedValues);

        $this->assertCount(count($expectedValues), $selectedValues);

        foreach ($expectedValues as $expectedValue) {
            $this->assertContains($expectedValue, $selectedValues);
        }
    }

    protected function assertSelect2HasNoSelection(Browser $browser, string $selectSelector): void
    {
        $this->assertSame([], $this->getSelect2Values($browser, $selectSelector));
    }

    /**
     * Setting a native <input type="date"> via ->type() sends keys to the browser's
     * segmented date widget, which is unreliable across locales and can produce
     * garbled values (e.g. typed digits landing in the wrong segment). Setting the
     * value directly and dispatching an 'input' event - which is what Vue's v-model
     * listens for on this input type - is the reliable equivalent.
     *
     * @param Browser $browser
     * @param string $selector
     * @param string $value Date string in 'Y-m-d' format
     */
    protected function setDateInput(Browser $browser, string $selector, string $value): void
    {
        $browser->script(
            'const el = document.querySelector(' . json_encode($selector) . ');'
            . 'el.value = ' . json_encode($value) . ';'
            . "el.dispatchEvent(new Event('input', { bubbles: true }));"
            . "el.dispatchEvent(new Event('change', { bubbles: true }));"
        );
    }
}
