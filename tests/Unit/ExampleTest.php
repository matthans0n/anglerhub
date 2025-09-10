<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
    
    /**
     * Test that our AnglerHub constants are defined.
     */
    public function test_angler_hub_constants(): void
    {
        $this->assertEquals('AnglerHub', config('app.name'));
    }
}