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

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.driver' => 'log']);
    }

    public function test_email_sent_by_an_existing_user_to_mailbox_is_stored_in_database(): void
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

    public function test_email_sent_by_a_non_existing_user_is_ignored(): void
    {
        $email = new TestMail(
            'nonexisting@email.address',
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        $this->assertCount(0, ReceivedMail::all());
    }

    public function test_email_sent_to_other_email_address_is_ignored(): void
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

    public function test_received_email_generates_event(): void
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

    public function test_email_without_subject_is_stored_with_default_subject(): void
    {
        // The related job is not executed in the test environment
        Queue::fake();

        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            ' ',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        $this->assertEquals(
            __('(No subject)'),
            ReceivedMail::where('user_id', $user->id)
                ->latest()
                ->first()
                ->subject
        );
    }
}
