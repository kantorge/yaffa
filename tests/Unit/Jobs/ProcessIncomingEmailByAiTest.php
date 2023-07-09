<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessIncomingEmailByAi;
use App\Models\ReceivedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ReflectionClass;

class ProcessIncomingEmailByAiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function test_cleanup_helper_removes_image_blocks()
    {
        // Create a string with image blocks
        $inputText = "
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.            
            [image: Image Block 1]
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            [image: Image Block 2]
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            [Not an image block]
        ";

        // Create a new received mail to invoke the cleanup helper
        $mail = new ReceivedMail();
        $processIncomingEmailByAi = new ProcessIncomingEmailByAi($mail);

        $reflection = new ReflectionClass(get_class($processIncomingEmailByAi));
        $method = $reflection->getMethod("cleanUpText");
        $method->setAccessible(true);
        $outputText = $method->invokeArgs($processIncomingEmailByAi, ['text' => $inputText]);

        // Assert that the output text does not contain any image blocks
        $this->assertStringNotContainsString('[image:', $outputText);

        // Assert that the custom block in brackets is not removed
        $this->assertStringContainsString('[Not an image block]', $outputText);
    }

    /**
     * @test
     */
    public function test_cleanup_helper_removes_link_tags()
    {
        // Create a string with link tags
        $inputText = "
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.            
            <https://some.link/?some=parameter>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            <https://some.other.link/?some=otherparam>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit.
            <Not a link tag>
        ";

        // Create a new received mail to invoke the cleanup helper
        $mail = new ReceivedMail();
        $processIncomingEmailByAi = new ProcessIncomingEmailByAi($mail);

        $reflection = new ReflectionClass(get_class($processIncomingEmailByAi));
        $method = $reflection->getMethod("cleanUpText");
        $method->setAccessible(true);
        $outputText = $method->invokeArgs($processIncomingEmailByAi, ['text' => $inputText]);

        // Assert that the output text does not contain any link tags
        $this->assertStringNotContainsString('<http', $outputText);

        // Assert that the custom block in brackets is not removed
        $this->assertStringContainsString('<Not a link tag>', $outputText);
    }
}
