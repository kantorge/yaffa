<?php

namespace Tests\Browser\Pages\Auth;

use App\Models\AiProviderConfig;
use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('extended')]
class AiProviderSettingsTest extends DuskTestCase
{

    const string VUE_COMPONENT_SELECTOR = '#aiProviderConfigForm';
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');

            static::$migrationRun = true;
        }

        // For all these tests, make sure to disable sandbox_mode environment settings so that these features are avialable on the UI
        $this->setConfig('yaffa.sandbox_mode', false);
    }

    private function visitSettings(Browser $browser): Browser
    {
        return $browser
            ->visit('/user/settings')
            ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
            ->waitForText('AI Provider Configuration', 10);
    }

    private function openAddProviderForm(Browser $browser): Browser
    {
        return $browser
            ->waitFor('@button-add-ai-provider', 10)
            ->scrollIntoView('@button-add-ai-provider')
            ->click('@button-add-ai-provider')
            ->waitFor('#provider', 10);
    }

    private function selectProviderAndModel(Browser $browser, string $provider, string $model): Browser
    {
        return $browser
            ->waitFor('#provider', 10)
            ->select('#provider', $provider)
            ->waitFor('#model', 10)
            ->waitFor("#model option[value=\"{$model}\"]", 10)
            ->select('#model', $model);
    }

    private function createTestUser(?string $email = null): User
    {
        if ($email === null) {
            $email = 'ai-config-test-'.uniqid().'@example.com';
        }

        return User::factory()->create([
            'email' => $email,
            'language' => 'en', // Ensure language is set for consistent testing
            'email_verified_at' => now(),
        ]);
    }

    public function test_add_provider_core_ui_behavior(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->assertSee('AI Provider Configuration')
                // Initial state assertion for no config
                ->assertSee('No AI provider configured yet.')
                ->assertSeeIn('@button-add-ai-provider', 'Add AI Provider')
                // Click to show form
                ->scrollIntoView('@button-add-ai-provider')
                ->click('@button-add-ai-provider')
                ->waitFor('#provider', 10)
                ->assertVisible('#provider');

            // Get available providers from config, and assert they are in the dropdown
            $providers = array_keys(config('ai-documents.providers'));
            foreach ($providers as $providerKey) {
                $browser->assertSeeIn('#provider', config("ai-documents.providers.$providerKey.name"));
            }

            // Get the first provider's models and assert model dropdown behavior
            $firstProviderKey = $providers[0];
            $modelsConfig = config("ai-documents.providers.$firstProviderKey.models");
            $models = array_is_list($modelsConfig) ? $modelsConfig : array_keys($modelsConfig);

            // Model dropdown is not visible until provider selected
            $browser->assertMissing('#model')
                ->select('#provider', $firstProviderKey)
                ->waitFor('#model', 10)
                ->assertVisible('#model')
                ->assertSeeIn('#model', 'Select model...');

            foreach ($models as $model) {
                $browser->assertSeeIn('#model', $model);
            }

            $visionModel = null;
            if (!array_is_list($modelsConfig)) {
                foreach ($modelsConfig as $modelName => $meta) {
                    if (!empty($meta['vision'])) {
                        $visionModel = $modelName;
                        break;
                    }
                }
            }

            // API Key input visibility and default state
            $browser->assertVisible('#api_key')
                ->assertInputValue('#api_key', '');

            if ($visionModel !== null) {
                $browser->select('#model', $visionModel)
                    ->waitFor('#vision_enabled', 10)
                    ->assertVisible('#vision_enabled');
            }

            // Cancel button functionality
            $browser->click('@button-cancel-add-ai-provider')
                ->waitUntilMissing('#provider', 10)
                ->assertSee('No AI provider configured yet.');
        });
    }

    public function test_can_create_ai_provider_config(): void
    {
         $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-test-1234567890abcdefghij')
                ->click('@button-save-ai-config')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'AI provider configuration created', 30)
                // Verify that the form shows the saved config (except API key)
                ->waitFor('#provider', 10)
                ->assertSelected('#provider', 'openai')
                ->assertSelected('#model', 'gpt-4o-mini')
                // API key field should be empty after save, with hint shown
                ->assertInputValue('#api_key', '')
                ->assertPresent('@api-key-hint');
        });
    }

    public function test_update_without_changing_api_key(): void
    {
        $user = $this->createTestUser();

        AiProviderConfig::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('#provider', 10);

            $browser->select('#model', 'gpt-4o')
                ->click('@button-save-ai-config')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'AI provider configuration updated', 30);
        });
    }

    public function test_update_with_new_api_key(): void
    {
        // Create or recreate existing config for the user (in case test order changes)
         $user = $this->createTestUser();

        AiProviderConfig::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-old-key-1234567890abcdefghij',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('#provider', 10)
                ->waitFor('#api_key', 10)
                ->type('#api_key', 'sk-new-key-1234567890abcdefghij')
                ->click('@button-save-ai-config')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'AI provider configuration updated', 30)
                // The API key field should be cleared after save
                ->assertInputValue('#api_key', '')
                ->assertPresent('@api-key-hint');
        });

        // Verify in database that the API key was updated
        $config = AiProviderConfig::where('user_id', $user->id)->first();
        $this->assertEquals('sk-new-key-1234567890abcdefghij', $config->api_key);
    }

    public function test_connection_button_is_enabled_when_form_filled(): void
    {
        $user = $this->createTestUser();
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-test-1234567890abcdefghij')
                ->assertPresent('@button-test-connection')
                ->assertEnabled('@button-test-connection');
        });
    }

    public function test_connection_shows_error_for_invalid_key(): void
    {
        $user = $this->createTestUser();
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-invalid-12345')
                ->click('@button-test-connection')
                ->waitFor('.alert.alert-danger', 10)
                ->assertPresent('.alert.alert-danger');
        });
    }

    public function test_connection_with_existing_config(): void
    {
        $user = $this->createTestUser();

        AiProviderConfig::factory()->create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $browser->waitFor('@button-test-connection', 10)
                ->click('@button-test-connection')
                ->waitFor('.alert', 10)
                // Will fail with invalid key but test should run
                ->assertPresent('.alert');
        });
    }

    public function test_can_delete_ai_provider_config_and_form_resets(): void
    {
        $user = $this->createTestUser();

        AiProviderConfig::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $browser->waitFor('@button-delete-ai-config', 10)
                ->assertVisible('@button-delete-ai-config')
                ->click('@button-delete-ai-config')
                // Accept confirmation dialog using SweetAlert2
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-confirm')
                // Verify success message appears
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('AI provider configuration deleted')
                // Refresh page to verify persistent state
                ->refresh()
                // Verify form resets to initial state
                ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
                ->assertSee('No AI provider configured yet.')
                ->assertSee('Add AI Provider');
        });
    }

    public function test_cancel_clears_form_data(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-test-1234567890abcdefghij')
                ->click('@button-cancel-add-ai-provider')
                ->waitUntilMissing('#provider', 10)
                ->waitFor('@button-add-ai-provider', 10)
                ->click('@button-add-ai-provider')
                ->waitFor('#provider', 10)
                ->assertSelected('#provider', '');
        });
    }

    public function test_multiple_create_update_delete_cycle(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            // Create
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-test-1234567890abcdefghij')
                ->click('@button-save-ai-config')
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('AI provider configuration created')
                ->waitUntilMissing('.toast-container div.toast.bg-success.show', 10);

            // Update
            $browser
                ->select('#provider', 'openai')
                ->select('#model', 'gpt-4o')
                ->click('@button-save-ai-config')
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('AI provider configuration updated')
                ->waitUntilMissing('.toast-container div.toast.bg-success.show', 10);

            // Delete
            $browser->click('@button-delete-ai-config')
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-confirm')
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('AI provider configuration deleted');

            // Verify back to initial state
            $browser->refresh()
                ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
                ->assertSee('No AI provider configured yet.');
        });
    }

    public function test_validation_error_shows_invalid_provider(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'short')
                ->click('@button-save-ai-config')
                ->waitForTextIn('div.toast-container div.toast.bg-danger.show', 'Validation failed. Please check the form for errors.', 30);
        });
    }

    public function test_provider_change_resets_test_result(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddProviderForm($browser);
            $this->selectProviderAndModel($browser, 'openai', 'gpt-4o-mini');

            $browser
                ->type('#api_key', 'sk-test-1234567890abcdefghij')
                ->click('@button-test-connection')
                ->waitFor('.alert', 10)
                ->select('#provider', 'gemini')
                ->waitUntilMissing('.alert', 10)
                ->assertNotPresent('.alert-danger');
        });
    }
}
