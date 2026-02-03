<?php

namespace Tests\Unit\Jobs;

use App\Listeners\CreateAiDocumentFromSource;
use ReflectionClass;
use Tests\TestCase;

class ProcessIncomingEmailByAiTest extends TestCase
{
    public function test_clean_html_content_removes_styles_svg_and_base64(): void
    {
        $listener = new CreateAiDocumentFromSource();

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('cleanHtmlContent');
        $method->setAccessible(true);

        $html = '<div style="color:red"><style>.x{}</style>'
            . '<svg><circle /></svg>'
            . '<img src="data:image/png;base64,AAA" />'
            . '<img src="https://example.com/image.png" />'
            . '<script>alert(1)</script>'
            . 'Hello</div>';

        $cleaned = $method->invoke($listener, $html);

        $this->assertStringNotContainsString('style=', $cleaned);
        $this->assertStringNotContainsString('<style', $cleaned);
        $this->assertStringNotContainsString('<svg', $cleaned);
        $this->assertStringNotContainsString('data:image', $cleaned);
        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringContainsString('Image: https://example.com/image.png', $cleaned);
        $this->assertStringContainsString('Hello', $cleaned);
    }
}
