<?php

namespace Tests\Unit\Models;

use App\Models\Priority;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_belongs_to_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $priority = Priority::factory()->for($workspace)->create();

        $this->assertTrue($priority->workspace->is($workspace));
    }

    public function test_defaults_are_seeded_with_levels_1_to_4(): void
    {
        $workspace = Workspace::factory()->create();
        Priority::seedDefaultsFor($workspace);

        $levels = $workspace->priorities()->pluck('level')->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4], $levels);
    }
}
