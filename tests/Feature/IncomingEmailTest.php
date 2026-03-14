<?php

namespace Tests\Feature;

use App\Events\EmailReceived;
use App\Events\DocumentImported;
use App\Listeners\CreateAiDocumentFromSource;
use App\Mail\TestMail;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\AiUserSettings;
use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        Event::fake(EmailReceived::class);

        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        Event::assertDispatched(
            EmailReceived::class,
            fn (EmailReceived $event) => $event->receivedMail->user_id === $user->id
        );
    }

    public function test_ai_document_listener_is_discovered_for_supported_events(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $receivedMail = ReceivedMail::factory()->for($user)->create();
        $aiDocument = AiDocument::factory()->for($user)->create();

        EmailReceived::dispatch($receivedMail);
        DocumentImported::dispatch($aiDocument);

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === CreateAiDocumentFromSource::class
                && $job->method === 'handleEmailReceived'
        );

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === CreateAiDocumentFromSource::class
                && $job->method === 'handleDocumentImported'
        );
    }

    public function test_received_email_does_not_queue_ai_processing_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $email = new TestMail(
            $user->email,
            'Test E-mail',
            'Some example text in the body',
        );

        Mail::to(config('yaffa.incoming_receipts_email'))->send($email);

        Queue::assertNotPushed(\App\Jobs\AiProcessingJob::class);
    }

    public function test_received_email_creates_ai_document_and_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        AiUserSettings::factory()->enabled()->create(['user_id' => $user->id]);

        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'subject' => 'Test Subject',
                'html' => '<p>Hello</p>',
                'text' => 'Hello',
            ]);

        $listener = new CreateAiDocumentFromSource(app(\App\Services\AiUserSettingsResolver::class));
        $listener->handleEmailReceived(new EmailReceived($mail));

        $document = AiDocument::first();

        $this->assertNotNull($document);
        $this->assertSame($user->id, $document->user_id);
        $this->assertSame('received_email', $document->source_type);
        $this->assertSame('ready_for_processing', $document->status);
        $this->assertSame($mail->id, $document->received_mail_id);

        $file = AiDocumentFile::first();

        $this->assertNotNull($file);
        Storage::disk('local')->assertExists($file->file_path);
        $this->assertSame('txt', $file->file_type);
    }

    public function test_received_email_does_not_create_ai_document_when_ai_is_disabled(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        AiUserSettings::factory()->create(['user_id' => $user->id, 'ai_enabled' => false]);

        $mail = ReceivedMail::factory()
            ->for($user)
            ->create([
                'subject' => 'Disabled AI subject',
                'html' => '<p>Hello</p>',
                'text' => 'Hello',
            ]);

        $listener = new CreateAiDocumentFromSource(app(\App\Services\AiUserSettingsResolver::class));
        $listener->handleEmailReceived(new EmailReceived($mail));

        $this->assertSame(0, AiDocument::count());
        $this->assertSame(0, AiDocumentFile::count());
    }

    public function test_email_without_subject_is_stored_with_default_subject(): void
    {
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
