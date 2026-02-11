<?php

namespace Tests\Browser\Pages\ReceivedMails;

use App\Models\ReceivedMail;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

const TABLESELECTOR = '#table';
const SELECTOR_TABLE_RECEIVED_MAILS = '@table-received-mails';

class ReceivedMailListTest extends DuskTestCase
{
    public function test_user_can_load_the_received_mail_list_and_use_filters(): void
    {
        $this->markTestSkipped('This feature will be completely redsigned in the next version, so it is no worth updating the test.');
    }

    public function test_user_can_interact_with_mail_action_buttons(): void
    {
        $this->markTestSkipped('This feature will be completely redsigned in the next version, so it is no worth updating the test.');
    }
}
