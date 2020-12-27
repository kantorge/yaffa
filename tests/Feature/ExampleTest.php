<?php

namespace Tests\Unit;

//use PHPUnit\Framework\TestCase;
use Tests\TestCase;

class ExampleTest extends TestCase
{

    public function setUp() :void
        {
            echo ("ExampleTest setup before\r\n");

            parent::setUp();

            echo ("ExampleTest setup after\r\n");
        }

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $this->assertTrue(true);
    }
}
