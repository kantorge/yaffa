<?php

namespace Tests\Browser\Pages\ReceivedMails;

use App\Models\ReceivedMail;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLESELECTOR = '#table';
class ReceivedMailListTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    public function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_the_received_mail_list_and_use_filters()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc')
            ->load('accounts');

        // Create test mails - 1 unprocessed, 2 processed, 1 handled
        ReceivedMail::factory()
            ->for($user)
            ->create([
                'processed' => false,
                'handled' => false,
            ]);

        ReceivedMail::factory()
            ->for($user)
            ->count(2)
            ->create([
                'processed' => true,
                'handled' => false,
                'transaction_data' => [],
            ]);

        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal()
            ->create();

        $handledMail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'subject' => 'Test mail with custom subject',
                'processed' => true,
                'handled' => true,
                'transaction_data' => [],
                'transaction_id' => $transaction->id,
            ]);

        $this->browse(function ($browser) use ($user, $handledMail) {
            // Acting as the main user
            $browser->loginAs($user)
                // Load the account list
                ->visitRoute('received-mail.index')
                // Wait for the table to load
                ->waitFor('@table-received-mails')
                // Check that the account list is visible
                ->assertPresent('@table-received-mails');

            // Get the number of mails in the table using JavaScript
            $this->assertEquals(
                $user->receivedMails()->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only unprocessed mails
            $browser->click('label[for=table_filter_processed_no]');
            $this->assertEquals(
                $user->receivedMails()->where('processed', false)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only processed mails
            $browser->click('label[for=table_filter_processed_yes]');
            $this->assertEquals(
                $user->receivedMails()->where('processed', true)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only handled mails
            $browser->click('label[for=table_filter_processed_any]');
            $browser->click('label[for=table_filter_handled_yes]');
            $this->assertEquals(
                $user->receivedMails()->where('handled', true)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table using the button bar to show only unhandled mails
            $browser->click('label[for=table_filter_handled_no]');
            $this->assertEquals(
                $user->receivedMails()->where('handled', false)->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table to show handled and unprocessed mails, which should be empty
            $browser->click('label[for=table_filter_handled_yes]');
            $browser->click('label[for=table_filter_processed_no]');
            $this->assertEquals(
                $user->receivedMails()
                    ->where('handled', true)
                    ->where('processed', false)
                    ->count(),
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            // Filter the table to show mails with specific search terms
            $browser->click('label[for=table_filter_handled_any]');
            $browser->click('label[for=table_filter_processed_any]');
            $browser->type('@input-table-filter-search', $handledMail->subject);
            $this->assertEquals(
                1,
                $this->getTableRowCount($browser, TABLESELECTOR)
            );

            $browser->clear('@input-table-filter-search');
            $browser->type('@input-table-filter-search', 'dummy search term');
            $this->assertEquals(
                0,
                $this->getTableRowCount($browser, TABLESELECTOR)
            );
        });
    }

    public function test_user_can_interact_with_mail_action_buttons()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // The same mails should be still present from the previous test

        // Test the view transaction modal
        $mail = ReceivedMail::whereNotNull('transaction_id')->first();

        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails');

            // Click the view transaction button
            $browser->click(TABLESELECTOR . ' button.transaction-quickview[data-id="' . $mail->transaction_id . '"]');
            // Wait for the modal to open
            $browser->waitFor('#modal-quickview', 10)
                ->assertSeeIn('#modal-quickview .modal-header', $mail->transaction_id);
        });

        // Test that the show transaction button exists
        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails')
                ->assertPresent(
                    TABLESELECTOR . ' a[href="' . route('transaction.open', [
                        'action' => 'show',
                        'transaction' => $mail->transaction_id
                    ]) . '"]'
                );
        });

        // Deleting a mail with a transaction will not delete the transaction
        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails');

            // Click the delete button
            $browser->click(TABLESELECTOR . ' button.deleteIcon[data-id="' . $mail->id . '"]')
                // Confirm the dialog
                ->acceptDialog()
                // Wait for the notification to appear
                ->waitFor('#BootstrapNotificationContainer .alert-success')
                // Check that the mail is gone from the table
                ->assertMissing(TABLESELECTOR . ' button.deleteIcon[data-id="' . $mail->id . '"]');

            // Check that the mail is gone from the database
            $this->assertDatabaseMissing('received_mails', [
                'id' => $mail->id,
            ]);

            // Check that the transaction still exists in the database
            $this->assertDatabaseHas('transactions', [
                'id' => $mail->transaction_id,
            ]);
        });

        // Unprocessed mails should not have a Finalize button
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails');

            // Filter the table to show only unprocessed mails
            $browser->click('label[for=table_filter_processed_no]');
            // Check that the finalize button is not present in the table
            $browser->assertMissing(TABLESELECTOR . ' button.finalizeIcon');
        });

        // Handled mails should not have a Finalize button
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails');

            // Filter the table to show only handled mails
            $browser->click('label[for=table_filter_handled_yes]');
            // Check that the finalize button is not present in the table
            $browser->assertMissing(TABLESELECTOR . ' button.finalizeIcon');
        });

        // Processed & unhandled mails should have a Finalize button
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visitRoute('received-mail.index')
                ->waitFor('@table-received-mails');

            // Filter the table to show only processed mails
            $browser->click('label[for=table_filter_processed_yes]');
            // Filter the table to show only unhandled mails
            $browser->click('label[for=table_filter_handled_no]');
            // Check that the finalize button is present in the table
            $browser->assertPresent(TABLESELECTOR . ' button.finalizeIcon');

            // Store the mail id for later from the data-id attribute
            $mailId = $browser->attribute(TABLESELECTOR . ' button.finalizeIcon:first-of-type', 'data-id');

            // Click the finalize button
            $browser->click(TABLESELECTOR . ' button.finalizeIcon:first-of-type')
                // Check that the finalize transaction route is loaded
                ->assertRouteIs('transactions.createFromDraft')
                // Wait for the transaction container to load
                ->waitFor('@transaction-container-standard')
                // Wait for Vue to load
                // TODO: This is a hack, find a better way to wait for Vue to load
                ->pause(10000)
                // Check that Vue has a sourceId set
                ->assertVue('sourceId', $mailId, '@transaction-container-standard');
        });
    }
}
