<?php

namespace Tests\Feature\Projects;

use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filing a record under a project, from the form rather than the database.
 *
 * The columns and the validation already existed, but no form offered the
 * field, so nothing could actually be filed. Meetings were the clearest case:
 * project_id was validated and saved and simply had no input behind it.
 */
class FileUnderProjectTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = $this->user->currentWorkspace();
        $this->project = Project::factory()->for($this->workspace)->create([
            'created_by' => $this->user->id,
            'title' => 'Platform rebuild',
        ]);
    }

    public static function pageProvider(): array
    {
        return [
            'meetings' => ['meetings.index', 'Meetings/Index'],
            'notes' => ['notes.index', 'Notes/Index'],
            'todos' => ['todos.index', 'Todos/Index'],
            'trips' => ['trips.index', 'Trips/Index'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_every_page_offers_the_projects_it_can_file_under(string $route, string $component): void
    {
        $this->actingAs($this->user)
            ->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->has('projects', 1)
                ->where('projects.0.title', 'Platform rebuild'));
    }

    public function test_an_archived_project_is_not_offered(): void
    {
        Project::factory()->for($this->workspace)->create([
            'created_by' => $this->user->id,
            'title' => 'Last year',
            'archived_at' => now(),
        ]);

        // Archived work is done with; offering it invites filing new records
        // against something nobody is looking at any more.
        $this->actingAs($this->user)
            ->get(route('notes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects', 1));
    }

    public function test_a_meeting_can_finally_be_filed_under_a_project(): void
    {
        $this->actingAs($this->user)
            ->post(route('meetings.store'), [
                'title' => 'Kickoff',
                'project_id' => $this->project->id,
                'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
                'meeting_type' => 'online',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'title' => 'Kickoff',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_a_record_can_be_filed_and_then_unfiled(): void
    {
        $this->actingAs($this->user)
            ->post(route('notes.store'), [
                'title' => 'Vendor pricing',
                'project_id' => $this->project->id,
            ])
            ->assertRedirect();

        $note = Note::firstWhere('title', 'Vendor pricing');
        $this->assertSame($this->project->id, $note->project_id);

        // The picker's "No project" option posts an empty value, which has to
        // land as a real null rather than failing validation.
        $this->actingAs($this->user)
            ->patch(route('notes.update', $note), ['title' => 'Vendor pricing', 'project_id' => null])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($note->fresh()->project_id);
    }

    public function test_the_lists_show_which_project_a_record_belongs_to(): void
    {
        $this->actingAs($this->user)->post(route('notes.store'), [
            'title' => 'Filed note',
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('notes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notes.0.project.title', 'Platform rebuild'));
    }

    public function test_a_project_from_another_workspace_cannot_be_chosen(): void
    {
        $foreign = Project::factory()->create();

        foreach (['notes.store', 'todos.store'] as $route) {
            $this->actingAs($this->user)
                ->post(route($route), ['title' => 'sneaky', 'project_id' => $foreign->id])
                ->assertSessionHasErrors('project_id');
        }
    }
}
