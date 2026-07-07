<?php

namespace Tests\Feature;

use App\Models\ReceivedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizeReceivedMailHtmlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sanitizes_existing_unsafe_html_in_place(): void
    {
        $user = User::factory()->create();

        $unsafe = ReceivedMail::factory()->for($user)->create([
            'html' => '<p>Hello</p><script>alert(document.cookie)</script><img src=x onerror="alert(1)">',
        ]);

        $safe = ReceivedMail::factory()->for($user)->create([
            'html' => '<p>Already <strong>clean</strong></p>',
        ]);

        $this->artisan('app:mail:sanitize-received-html')->assertExitCode(0);

        $unsafe->refresh();
        $safe->refresh();

        $this->assertStringNotContainsString('<script', $unsafe->html);
        $this->assertStringNotContainsString('onerror', $unsafe->html);
        $this->assertStringContainsString('Hello', $unsafe->html);

        $this->assertSame('<p>Already <strong>clean</strong></p>', $safe->html);
    }

    public function test_dry_run_reports_without_modifying_records(): void
    {
        $user = User::factory()->create();

        $unsafe = ReceivedMail::factory()->for($user)->create([
            'html' => '<script>alert(1)</script><p>Hi</p>',
        ]);

        $originalHtml = $unsafe->html;

        $this->artisan('app:mail:sanitize-received-html', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame($originalHtml, $unsafe->fresh()->html);
    }
}
