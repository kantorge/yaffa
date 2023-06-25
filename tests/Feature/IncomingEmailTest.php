<?php

namespace Tests\Feature;

use App\Events\IncomingEmailReceived;
use App\Listeners\ProcessIncomingEmail;
use App\Mail\TestMail;
use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IncomingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        config(['mail.driver' => 'log']);
    }

    /** @test */
    public function email_sent_by_an_existing_user_to_mailbox_is_stored_in_database()
    {
        // The related job is not executed in the test environment
        Queue::fake();

        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        $this->assertCount(1, ReceivedMail::all());
    }

    /** @test */
    public function email_sent_by_a_non_existing_user_is_ignored()
    {
        $email = new TestMail(
            'nonexisting@email.address',
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        $this->assertCount(0, ReceivedMail::all());
    }

    /** @test */
    public function email_sent_to_other_email_address_is_ignored()
    {
        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to('dummy@yaffa.test')->send($email);

        $this->assertCount(0, ReceivedMail::all());
    }

    /** @test */
    public function received_email_generates_event()
    {
        Event::fake(IncomingEmailReceived::class);

        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        Event::assertDispatched(
            IncomingEmailReceived::class,
            fn (IncomingEmailReceived $event) => $event->mail->user_id === $user->id
        );

        Event::assertListening(
            IncomingEmailReceived::class,
            ProcessIncomingEmail::class
        );
    }

}
