<?php

namespace Tests\Unit\Models;

use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_belongs_to_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $tag = Tag::factory()->for($workspace)->create();

        $this->assertTrue($tag->workspace->is($workspace));
    }

    public function test_slug_is_unique_per_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        Tag::factory()->for($workspace)->create(['name' => 'Urgent']);
        $second = Tag::factory()->for($workspace)->create(['name' => 'Urgent']);

        $this->assertNotSame('urgent', $second->slug);
    }
}
