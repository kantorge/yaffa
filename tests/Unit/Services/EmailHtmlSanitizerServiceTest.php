<?php

namespace Tests\Unit\Services;

use App\Services\EmailHtmlSanitizerService;
use Tests\TestCase;

class EmailHtmlSanitizerServiceTest extends TestCase
{
    private EmailHtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new EmailHtmlSanitizerService();
    }

    public function test_it_strips_script_tags(): void
    {
        $result = $this->sanitizer->sanitize('<p>Hello</p><script>alert(document.cookie)</script>');

        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $result = $this->sanitizer->sanitize('<img src="x" onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $result);
    }

    public function test_it_strips_javascript_uri_from_links(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">click me</a>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_it_preserves_safe_formatting_tags(): void
    {
        $result = $this->sanitizer->sanitize('<p><strong>Bold</strong> and <em>italic</em></p>');

        $this->assertStringContainsString('<strong>Bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
    }

    public function test_it_preserves_safe_links(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com">link</a>');

        $this->assertStringContainsString('href="https://example.com"', $result);
    }
}
