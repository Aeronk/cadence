<?php

namespace Tests\Unit\Models;

use App\Models\Status;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_belongs_to_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $status = Status::factory()->for($workspace)->create();

        $this->assertTrue($status->workspace->is($workspace));
    }

    public function test_slug_is_unique_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Status::factory()->for($workspace)->create(['name' => 'Custom Stage']);
        $second = Status::factory()->for($workspace)->create(['name' => 'Custom Stage']);

        $this->assertNotSame('custom-stage', $second->slug);
        $this->assertStringStartsWith('custom-stage-', $second->slug);
    }

    public function test_same_slug_allowed_in_different_workspaces(): void
    {
        $a = Workspace::factory()->create();
        $b = Workspace::factory()->create();

        $s1 = Status::factory()->for($a)->create(['name' => 'Review Stage']);
        $s2 = Status::factory()->for($b)->create(['name' => 'Review Stage']);

        $this->assertSame('review-stage', $s1->slug);
        $this->assertSame('review-stage', $s2->slug);
    }

    public function test_defaults_are_seeded_for_a_new_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Status::seedDefaultsFor($workspace);

        $slugs = $workspace->statuses()->pluck('slug')->all();
        $this->assertContains('backlog', $slugs);
        $this->assertContains('in-progress', $slugs);
        $this->assertContains('done', $slugs);
    }
}
