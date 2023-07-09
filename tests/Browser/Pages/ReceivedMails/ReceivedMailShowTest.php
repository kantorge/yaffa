<?php

namespace Tests\Browser\Pages\ReceivedMails;

use App\Models\ReceivedMail;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ReceivedMailShowTest extends DuskTestCase
{
    public function test_user_can_load_the_received_mail_show_page_for_unprocessed_mail()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Generate a new unprocessed mail
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'processed' => false,
            ]);

        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the received mail show page
                ->visitRoute('received-mail.show', ['received_mail' => $mail->id])
                // Wait for the page to load
                ->waitFor('div.body')

                // Validate the processed icon
                ->assertPresent('@icon-received-mail-processed-no')
                // Validate the handled icon
                ->assertPresent('@icon-received-mail-handled-no')
                // Validate the linked transaction icon
                ->assertPresent('@icon-received-mail-transaction-no')

                // Validate the delete button
                ->assertPresent('@button-received-mail-delete')

                // Validate that the finalize button is not present
                ->assertMissing('@button-received-mail-finalize')

                // Validate that the reset processed button is not present
                ->assertMissing('@button-received-mail-reprocess')

                // Validate that the transaction data is not present, and the tab is disabled
                ->assertMissing('@received-mail-tab-data')
                ->assertAttributeContains('@button-received-mail-tab-data', 'class', 'disabled')

                // Validate that the HTML and text tabs are present, and the HTML tab is active
                ->assertPresent('@received-mail-tab-html')
                ->assertPresent('@received-mail-tab-text')
                ->assertAttributeContains('@button-received-mail-tab-html', 'class', 'active')
                ->assertAttributeContains('@received-mail-tab-html', 'class', 'active');

            // Delete the mail
            $browser->waitForReload(function (Browser $browser) {
                // Click the "Delete" button
                $browser
                    ->click('@button-received-mail-delete')
                    ->waitForDialog()
                    ->acceptDialog();
            })
                ->assertRouteIs('received-mail.index');

            // Check that the mail is not in the database anymore
            $this->assertDatabaseMissing('received_mails', [
                'id' => $mail->id,
            ]);
        });
    }

    public function test_user_can_load_the_received_mail_show_page_for_processed_unhadled_mail()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Generate a new unhadled mail
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'processed' => true,
                'handled' => false,
                'transaction_data' => [
                    "raw" => [
                        "date" => "2023-05-29",
                        "type" => "deposit",
                        "payee" => "McDonald's - Budaörs",
                        "amount" => "9179.00",
                        "account" => null,
                        "currency" => "Ft",
                        "payee_id" => 385,
                        "account_id" => null,
                        "transaction_type_id" => 2
                    ],
                    "date" => "2023-05-29",
                    "config" => [
                        "amount_to" => 9179.00,
                        "amount_from" => 9179.00,
                        "account_to_id" => null,
                        "account_from_id" => 385
                    ],
                    "config_type" => "transaction_detail_standard",
                    "transaction_type" => [
                        "name" => "withdrawal"
                    ],
                    "transaction_type_id" => 2
                ]
            ]);

        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the received mail show page
                ->visitRoute('received-mail.show', ['received_mail' => $mail->id])
                // Wait for the page to load
                ->waitFor('div.body')

                // Validate the processed icon
                ->assertPresent('@icon-received-mail-processed-yes')
                // Validate the handled icon
                ->assertPresent('@icon-received-mail-handled-no')
                // Validate the linked transaction icon
                ->assertPresent('@icon-received-mail-transaction-no')

                // Validate the delete button
                ->assertPresent('@button-received-mail-delete')

                // Validate that the finalize button is present
                ->assertPresent('@button-received-mail-finalize')

                // Validate that the reset processed button is present
                ->assertPresent('@button-received-mail-reprocess')

                // Validate that the transaction data is present
                ->assertPresent('@received-mail-tab-data')

                // Validate that the HTML and text tabs are present, and the HTML tab is active
                ->assertPresent('@received-mail-tab-html')
                ->assertPresent('@received-mail-tab-text')
                ->assertAttributeContains('@button-received-mail-tab-html', 'class', 'active')
                ->assertAttributeContains('@received-mail-tab-html', 'class', 'active');

            // TODO: validate the content of the three tabs

            // Validate the behavior of the finalize button
            $browser->waitForReload(function (Browser $browser) {
                $browser->click('@button-received-mail-finalize');
            })
                ->assertRouteIs('transactions.createFromDraft');
        });
    }

    public function test_user_can_reset_the_processed_flag_of_a_mail()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Generate a new unhadled mail
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'processed' => true,
                'handled' => false,
                'transaction_data' => [
                    "raw" => [
                        "date" => "2023-05-29",
                        "type" => "deposit",
                        "payee" => "McDonald's - Budaörs",
                        "amount" => "9179.00",
                        "account" => null,
                        "currency" => "Ft",
                        "payee_id" => 385,
                        "account_id" => null,
                        "transaction_type_id" => 2
                    ],
                    "date" => "2023-05-29",
                    "config" => [
                        "amount_to" => 9179.00,
                        "amount_from" => 9179.00,
                        "account_to_id" => null,
                        "account_from_id" => 385
                    ],
                    "config_type" => "transaction_detail_standard",
                    "transaction_type" => [
                        "name" => "withdrawal"
                    ],
                    "transaction_type_id" => 2
                ]
            ]);

        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser
            // Acting as the main user
                ->loginAs($user)
                // Load the received mail show page
                ->visitRoute('received-mail.show', ['received_mail' => $mail->id])
                // Wait for the page to load
                ->waitFor('div.body')

                // Press the reset processed button
                ->click('@button-received-mail-reprocess')

                // Accept the confirmation dialog
                ->acceptDialog()

                // Wait for the page to reload
                ->waitForReload()

                // Validate the processed icon
                ->assertPresent('@icon-received-mail-processed-no');
        });
    }

    public function test_user_can_load_the_received_mail_show_page_for_processed_handled_mail()
    {
        // Load the main test user
        $user = User::firstWhere('email', 'demo@yaffa.cc');

        // Generate a new handled mail
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal()
            ->create();

        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'processed' => true,
                'handled' => true,
                'transaction_data' => [
                    "raw" => [
                        "date" => "2023-05-29",
                        "type" => "deposit",
                        "payee" => "McDonald's - Budaörs",
                        "amount" => "9179.00",
                        "account" => null,
                        "currency" => "Ft",
                        "payee_id" => 385,
                        "account_id" => null,
                        "transaction_type_id" => 2
                    ],
                    "date" => "2023-05-29",
                    "config" => [
                        "amount_to" => 9179.00,
                        "amount_from" => 9179.00,
                        "account_to_id" => null,
                        "account_from_id" => 385
                    ],
                    "config_type" => "transaction_detail_standard",
                    "transaction_type" => [
                        "name" => "withdrawal"
                    ],
                    "transaction_type_id" => 2
                ],
                'transaction_id' => $transaction->id,
            ]);

        $this->browse(function (Browser $browser) use ($user, $mail) {
            $browser
                // Acting as the main user
                ->loginAs($user)
                // Load the received mail show page
                ->visitRoute('received-mail.show', ['received_mail' => $mail->id])
                // Wait for the page to load
                ->waitFor('div.body')

                // Validate the processed icon
                ->assertPresent('@icon-received-mail-processed-yes')
                // Validate the handled icon
                ->assertPresent('@icon-received-mail-handled-yes')
                // Validate the linked transaction anchor
                ->assertPresent('@link-received-mail-transaction')

                // Validate the delete button
                ->assertPresent('@button-received-mail-delete')

                // Validate that the finalize button is not present
                ->assertMissing('@button-received-mail-finalize')

                // Validate that the reset processed button is not present
                ->assertMissing('@button-received-mail-reprocess')

                // Validate that the transaction data is present
                ->assertPresent('@received-mail-tab-data')

                // Validate that the HTML and text tabs are present, and the HTML tab is active
                ->assertPresent('@received-mail-tab-html')
                ->assertPresent('@received-mail-tab-text')
                ->assertAttributeContains('@button-received-mail-tab-html', 'class', 'active')
                ->assertAttributeContains('@received-mail-tab-html', 'class', 'active');
        });
    }
}
