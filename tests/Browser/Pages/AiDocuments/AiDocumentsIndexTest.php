<?php

namespace Tests\Browser\Pages\AiDocuments;

use App\Models\AiDocument;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * @group extended
 */
class AiDocumentsIndexTest extends DuskTestCase
{
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
    }

    public function test_ai_documents_applies_date_preset_on_load(): void
    {
        $user = User::factory()->create([
            'email' => 'ai-documents-test-' . uniqid() . '@example.com',
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        AiDocument::factory()->for($user)->create([
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            // Open the AI documents page with the "previous7Days" preset
            $browser->visitRoute('ai-documents.index', [
                'date_preset' => 'previous7Days',
            ])
                ->waitFor('#ai-document-table')
                ->waitFor('#aiDocumentDatePresets')
                // Verify that the preset dropdown shows the selected preset
                ->assertValue('#aiDocumentDatePresets', 'previous7Days');
        });
    }

    public function test_ai_documents_applies_date_range_on_load(): void
    {
        $user = User::factory()->create([
            'email' => 'ai-documents-test-' . uniqid() . '@example.com',
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        $targetDate = now()->addYears(1);

        AiDocument::factory()->for($user)->create([
            'created_at' => $targetDate,
        ]);

        $this->browse(function (Browser $browser) use ($user, $targetDate) {
            $browser->loginAs($user);
            // Open the AI documents page with explicit date range parameters
            $browser->visitRoute('ai-documents.index', [
                'date_from' => $targetDate->copy()->subDays(1)->format('Y-m-d'),
                'date_to' => $targetDate->copy()->addDays(1)->format('Y-m-d'),
            ])
                ->waitFor('#ai-document-table')
                ->waitFor('#aiDocumentDate_from')
                // Verify that the date fields are populated
                ->assertValue('#aiDocumentDate_from', $targetDate->copy()->subDays(1)->format('Y-m-d'))
                ->assertValue('#aiDocumentDate_to', $targetDate->copy()->addDays(1)->format('Y-m-d'))
                // Verify the preset dropdown shows "Select preset" (none) when explicit dates are used
                ->assertValue('#aiDocumentDatePresets', 'none');
        });
    }

    public function test_ai_documents_date_filter_updates_table(): void
    {
        $user = User::factory()->create([
            'email' => 'ai-documents-test-' . uniqid() . '@example.com',
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        $today = now();

        AiDocument::factory()->for($user)->create([
            'created_at' => $today,
        ]);

        $this->browse(function (Browser $browser) use ($user, $today) {
            $browser->loginAs($user);
            $browser->visitRoute('ai-documents.index')
                ->waitFor('#ai-document-table')
                ->waitFor('#aiDocumentDate_from');

            // Set a date range - dates are automatically updated as user types
            $browser
                ->type('#aiDocumentDate_from', $today->copy()->subDays(1)->format('Y-m-d'))
                ->type('#aiDocumentDate_to', $today->format('Y-m-d'))
                ->pause(1000)
                // Verify the dates are set in the input fields
                ->assertValue('#aiDocumentDate_from', $today->copy()->subDays(1)->format('Y-m-d'))
                ->assertValue('#aiDocumentDate_to', $today->format('Y-m-d'));
        });
    }

    public function test_ai_documents_preset_dropdown_populated_correctly(): void
    {
        $user = User::factory()->create([
            'email' => 'ai-documents-test-' . uniqid() . '@example.com',
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        AiDocument::factory()->for($user)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user);
            $browser->visitRoute('ai-documents.index', [
                'date_preset' => 'previous30Days',
            ])
                ->waitFor('#ai-document-table')
                ->waitFor('#aiDocumentDatePresets')
                // Verify the preset dropdown shows the selected preset
                ->assertValue('#aiDocumentDatePresets', 'previous30Days')
                ->assertSeeIn('#aiDocumentDatePresets', 'Previous 30 days');
        });
    }
}
