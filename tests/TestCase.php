<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        error_reporting(error_reporting() & ~E_DEPRECATED);
        parent::setUp();
    }
}
