<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Skip Vite during tests — missing built assets shouldn't break HTML rendering.
        $this->withoutVite();

        // Don't require Vue page files to exist on disk for assertInertia to work.
        config()->set('inertia.testing.ensure_pages_exist', false);
    }
}
