<?php

namespace Tests\Browser\Pages\Auth;

use App\Models\GoogleDriveConfig;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class GoogleDriveSettingsTest extends DuskTestCase
{
    private const string VUE_COMPONENT_SELECTOR = '#googleDriveConfigForm';

    private const string VALID_SERVICE_ACCOUNT_JSON = '{"type":"service_account","project_id":"test-project","private_key_id":"key123","private_key":"-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----","client_email":"test@test-project.iam.gserviceaccount.com","client_id":"123456789","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (! static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');

            static::$migrationRun = true;
        }
    }

    private function visitSettings(Browser $browser): Browser
    {
        return $browser
            ->visit('/user/settings')
            ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
            ->waitForText('Google Drive Configuration', 10);
    }

    private function openAddConfigForm(Browser $browser): Browser
    {
        return $browser
            ->waitFor('@button-add-google-drive', 10)
            ->scrollIntoView('@button-add-google-drive')
            ->click('@button-add-google-drive')
            ->waitFor('#service_account_json', 10);
    }

    private function createTestUser(?string $email = null): User
    {
        if ($email === null) {
            $email = 'google-drive-test-' . uniqid() . '@example.com';
        }

        return User::factory()->create([
            'email' => $email,
            'language' => 'en',
            'email_verified_at' => now(),
        ]);
    }

    public function test_add_config_core_ui_behavior(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->assertSee('Google Drive Configuration')
                ->assertSee('No Google Drive configuration yet.')
                ->assertSeeIn('@button-add-google-drive', 'Add Google Drive')
                ->scrollIntoView('@button-add-google-drive')
                ->click('@button-add-google-drive')
                ->waitFor('#service_account_json', 10)
                ->assertVisible('#service_account_json')
                ->assertVisible('#folder_id')
                ->assertVisible('#delete_after_import')
                ->assertVisible('#enabled')
                ->assertVisible('@button-cancel-add-google-drive');
        });
    }

    public function test_can_create_google_drive_config(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('#service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('folder_id', 'test-folder-id-123')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration created', 10)
                ->waitFor('#service_account_json', 10)
                ->assertInputValue('#folder_id', 'test-folder-id-123')
                ->assertInputValue('#service_account_json', '')
                ->assertPresent('@service-account-json-hint')
                ->assertSeeIn('@service-account-email', 'test@test-project.iam.gserviceaccount.com');
        });
    }

    public function test_folder_id_extracts_from_full_url(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            // Test URL with /folders/ pattern
            $browser
                // Normally, the user would paste the URL, but for testing we need to use type() to trigger the Vue watcher and normalization logic
                ->type('folder_id', 'https://drive.google.com/drive/folders/1abcdefg123456-FOLDER_ID_HERE')
                // The normalization happens on blur, so we need to wait and check the value after blur
                ->click('#service_account_json') // Click elsewhere to trigger blur
                ->assertInputValue('#folder_id', '1abcdefg123456-FOLDER_ID_HERE')
                // Now type just the folder ID
                ->clear('folder_id')
                ->type('folder_id', 'simple-folder-id')
                ->click('#service_account_json') // Trigger blur again
                ->assertInputValue('#folder_id', 'simple-folder-id');
        });
    }

    public function test_update_without_changing_service_account_json(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'old-folder-id',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('#folder_id', 10)
                ->clear('#folder_id')
                ->type('#folder_id', 'new-folder-id')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration updated', 10);
        });
    }

    public function test_update_with_new_service_account_json(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'test-folder-id',
        ]);

        $newJson = '{"type":"service_account","project_id":"new-project","private_key_id":"newkey","private_key":"-----BEGIN PRIVATE KEY-----\nnewtest\n-----END PRIVATE KEY-----","client_email":"new@new-project.iam.gserviceaccount.com","client_id":"987654321","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token"}';

        $this->browse(function (Browser $browser) use ($user, $newJson) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('#service_account_json', 10)
                ->type('#service_account_json', $newJson)
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration updated', 10)
                ->assertInputValue('#service_account_json', '')
                ->assertPresent('@service-account-json-hint')
                ->assertSeeIn('@service-account-email', 'new@new-project.iam.gserviceaccount.com');
        });

        // Verify in database that the JSON was updated
        $config = GoogleDriveConfig::where('user_id', $user->id)->first();
        $this->assertEquals($newJson, $config->service_account_json);
        $this->assertEquals('new@new-project.iam.gserviceaccount.com', $config->service_account_email);
    }

    public function test_test_button_is_enabled_when_form_filled(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('#service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('#folder_id', 'test-folder-id')
                ->assertPresent('@button-test-google-drive')
                ->assertEnabled('@button-test-google-drive');
        });
    }

    public function test_test_connection_shows_error_for_invalid_credentials(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('#service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('#folder_id', 'invalid-folder-id')
                ->scrollIntoView('@button-test-google-drive')
                ->click('@button-test-google-drive')
                ->waitFor('.alert.alert-danger', 10)
                ->assertPresent('.alert.alert-danger');
        });
    }

    public function test_test_connection_with_existing_config(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'service_account_json' => self::VALID_SERVICE_ACCOUNT_JSON,
            'folder_id' => 'test-folder-id',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('@button-test-google-drive', 10)
                ->click('@button-test-google-drive')
                ->waitFor('.alert', 10)
                ->assertPresent('.alert');
        });
    }

    public function test_can_delete_google_drive_config_and_form_resets(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('@button-delete-google-drive', 10)
                ->assertVisible('@button-delete-google-drive')
                ->click('@button-delete-google-drive')
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-confirm')
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('Google Drive configuration deleted')
                ->refresh()
                ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
                ->assertSee('No Google Drive configuration yet.')
                ->assertSee('Add Google Drive');
        });
    }

    public function test_cancel_clears_form_data(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('#service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('#folder_id', 'test-folder-id')
                ->click('@button-cancel-add-google-drive')
                ->waitUntilMissing('#folder_id', 10)
                ->waitFor('@button-add-google-drive', 10)
                ->click('@button-add-google-drive')
                ->waitFor('#folder_id', 10)
                ->assertInputValue('#folder_id', '')
                ->assertInputValue('#service_account_json', '');
        });
    }

    public function test_multiple_create_update_delete_cycle(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);

            // Create
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('folder_id', 'folder-id-1')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration created', 10)
                ->waitUntilMissing('.toast-container div.toast.bg-success.show', 10);

            // Update
            $browser
                ->clear('folder_id')
                ->type('folder_id', 'folder-id-2')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration updated', 10)
                ->waitUntilMissing('.toast-container div.toast.bg-success.show', 10);

            // Delete
            $browser->click('@button-delete-google-drive')
                ->waitFor('.swal2-container', 10)
                ->click('.swal2-confirm')
                ->waitFor('.toast-container div.toast.bg-success.show', 10)
                ->assertSee('Google Drive configuration deleted');

            // Verify back to initial state
            $browser->refresh()
                ->waitFor(self::VUE_COMPONENT_SELECTOR, 10)
                ->assertSee('No Google Drive configuration yet.');
        });
    }

    public function test_validation_error_shows_for_invalid_json(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('service_account_json', 'invalid json')
                ->type('folder_id', 'test-folder-id')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-danger.show', 'Validation failed. Please check the form for errors.', 30);
        });
    }

    public function test_manual_sync_button_appears_for_existing_config(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('@button-sync-google-drive', 10)
                ->assertPresent('@button-sync-google-drive')
                ->assertSeeIn('@button-sync-google-drive', 'Manual Sync');
        });
    }

    public function test_manual_sync_shows_not_implemented_message(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create(['user_id' => $user->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('@button-sync-google-drive', 10)
                ->click('@button-sync-google-drive')
                ->waitFor('.toast-container div.toast.bg-info.show', 10)
                ->assertSee('Google Drive sync has been queued');
        });
    }

    public function test_delete_after_import_toggle_works(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('folder_id', 'test-folder-id')
                ->assertNotChecked('#delete_after_import')
                ->click('#delete_after_import')
                ->assertChecked('#delete_after_import')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration created', 30);
        });

        // Verify in database
        $config = GoogleDriveConfig::where('user_id', $user->id)->first();
        $this->assertTrue($config->delete_after_import);
    }

    public function test_enabled_toggle_works(): void
    {
        $user = $this->createTestUser();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser);
            $this->openAddConfigForm($browser);

            $browser
                ->type('service_account_json', self::VALID_SERVICE_ACCOUNT_JSON)
                ->type('folder_id', 'test-folder-id')
                ->assertChecked('#enabled')
                ->click('#enabled')
                ->assertNotChecked('#enabled')
                ->click('@button-save-google-drive')
                ->waitForTextIn('div.toast-container div.toast.bg-success.show', 'Google Drive configuration created', 30);
        });

        // Verify in database
        $config = GoogleDriveConfig::where('user_id', $user->id)->first();
        $this->assertFalse($config->enabled);
    }

    public function test_last_sync_displays_never_when_not_synced(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'last_sync_at' => null,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('@last-sync-at', 10)
                ->assertSeeIn('@last-sync-at', 'Never');
        });
    }

    public function test_last_error_displays_when_present(): void
    {
        $user = $this->createTestUser();

        GoogleDriveConfig::factory()->create([
            'user_id' => $user->id,
            'last_error' => 'Test error message',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $this->visitSettings($browser)
                ->waitFor('.alert.alert-warning', 10)
                ->assertSee('Test error message');
        });
    }
}
