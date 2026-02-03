<?php

namespace App\Console\Commands;

use App\Components\MailHandler;
use App\Events\EmailReceived;
use App\Listeners\CreateAiDocumentFromSource;
use App\Models\ReceivedMail;
use App\Models\User;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class SimulateIncomingEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:simulate-incoming-email
        {--from= : Sender email address (must belong to an existing user unless --create-user is used)}
        {--subject= : Email subject}
        {--text= : Plain-text body}
        {--html= : HTML body}
        {--message-id= : Message ID override}
        {--user-id= : User ID to associate the email with}
        {--create-user : Create the user if it does not exist}
        {--sync : Also run the AI document creation listener synchronously}
        {--use-demo : Use demo@yaffa.cc and create the user if missing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an incoming mailbox email and store it as a received mail';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $messageId = (string) ($this->option('message-id') ?: Str::uuid());
        $subject = (string) ($this->option('subject') ?: 'Test subject');
        $text = $this->option('text');
        $html = $this->option('html');

        if ($text === null && $html === null) {
            $text = 'Plain text body';
        }

        if ($this->option('use-demo')) {
            $this->input->setOption('from', 'demo@yaffa.cc');
            $this->input->setOption('create-user', true);
        }

        $user = $this->resolveUser();
        if (! $user) {
            $this->error('User not found. Provide --from or --user-id, or use --create-user.');
            return self::FAILURE;
        }

        $fromEmail = (string) ($this->option('from') ?: $user->email);

        $handler = app(MailHandler::class);

        if ($this->option('sync')) {
            Event::fake([EmailReceived::class]);
        }

        $rawMessage = $this->buildRawMessage(
            $fromEmail,
            $subject,
            $messageId,
            $text,
            $html
        );

        $email = InboundEmail::fromMessage($rawMessage);

        $handler($email);

        $receivedMail = ReceivedMail::where('message_id', $messageId)->latest()->first();

        if (! $receivedMail) {
            $this->error('Failed to create received mail.');
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $listener = app(CreateAiDocumentFromSource::class);
            $listener->handleEmailReceived(new EmailReceived($receivedMail));
        }

        $this->info('ReceivedMail created: ID ' . $receivedMail->id);

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user-id');
        if ($userId) {
            return User::find($userId);
        }

        $from = $this->option('from');
        if (! $from) {
            return null;
        }

        $user = User::where('email', $from)->first();
        if ($user) {
            return $user;
        }

        if (! $this->option('create-user')) {
            return null;
        }

        $name = Str::before((string) $from, '@');

        return User::factory()->create([
            'name' => $name ?: 'Mailbox User',
            'email' => $from,
        ]);
    }

    private function buildRawMessage(
        string $from,
        string $subject,
        string $messageId,
        ?string $text,
        ?string $html
    ): string {
        $headers = [
            'Message-Id: <' . $messageId . '>',
            'From: ' . $from,
            'To: ' . config('yaffa.incoming_receipts_email'),
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
        ];

        if ($text !== null && $html !== null) {
            $boundary = '=_yaffa_' . Str::random(12);

            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            return implode("\r\n", $headers)
                . "\r\n\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=utf-8\r\n\r\n"
                . $text . "\r\n"
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=utf-8\r\n\r\n"
                . $html . "\r\n"
                . "--{$boundary}--\r\n";
        }

        if ($html !== null) {
            $headers[] = 'Content-Type: text/html; charset=utf-8';

            return implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n";
        }

        $headers[] = 'Content-Type: text/plain; charset=utf-8';

        return implode("\r\n", $headers) . "\r\n\r\n" . ($text ?? '') . "\r\n";
    }
}
