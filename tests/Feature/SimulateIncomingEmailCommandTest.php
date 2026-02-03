<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\ReceivedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimulateIncomingEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_received_mail_and_document_when_sync_enabled(): void
    {
        Storage::fake('local');

        $email = 'simulate@yaffa.test';

        $this->artisan('ai:simulate-incoming-email', [
            '--from' => $email,
            '--subject' => 'Test subject',
            '--text' => 'Plain text body',
            '--sync' => true,
            '--create-user' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);

        $this->assertSame(1, ReceivedMail::count());
        $this->assertSame(1, AiDocument::count());
        $this->assertSame(1, AiDocumentFile::count());

        $file = AiDocumentFile::first();
        Storage::disk('local')->assertExists($file->file_path);
    }

    public function test_command_supports_demo_user_defaults(): void
    {
        Storage::fake('local');

        $this->artisan('ai:simulate-incoming-email', [
            '--use-demo' => true,
            '--sync' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => 'demo@yaffa.cc',
        ]);

        $this->assertSame(1, ReceivedMail::count());
        $this->assertSame(1, AiDocument::count());
        $this->assertSame(1, AiDocumentFile::count());
    }
}
