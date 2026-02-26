<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimulateIncomingEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_received_mail_and_document_when_sync_enabled(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->artisan('app:simulate-incoming-email', [
            '--from' => $user->email,
            '--subject' => 'Test subject',
            '--text' => 'Plain text body',
            '--sync' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => $user->email,
        ]);

        $this->assertSame(1, ReceivedMail::count());
        $this->assertSame(1, AiDocument::count());
        $this->assertSame(1, AiDocumentFile::count());

        $file = AiDocumentFile::first();
        Storage::disk('local')->assertExists($file->file_path);
    }
}
