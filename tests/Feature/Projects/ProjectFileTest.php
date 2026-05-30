<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_upload_a_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $upload = UploadedFile::fake()->create('spec.pdf', 200, 'application/pdf');

        $this->actingAs($user)
            ->post(route('projects.files.store', $project), ['file' => $upload])
            ->assertRedirect();

        $this->assertDatabaseHas('project_files', [
            'project_id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'uploaded_by' => $user->id,
            'original_name' => 'spec.pdf',
        ]);

        $stored = ProjectFile::first();
        Storage::disk($stored->disk)->assertExists($stored->path);
    }

    public function test_disallowed_mime_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $upload = UploadedFile::fake()->create('boot.exe', 100, 'application/x-msdownload');

        $this->actingAs($user)
            ->post(route('projects.files.store', $project), ['file' => $upload])
            ->assertStatus(422);

        $this->assertDatabaseCount('project_files', 0);
    }

    public function test_outsider_cannot_upload(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('projects.files.store', $project), [
                'file' => UploadedFile::fake()->create('spec.pdf', 50, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_member_can_download_their_project_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('projects.files.store', $project), [
            'file' => UploadedFile::fake()->create('spec.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $file = ProjectFile::first();

        $this->actingAs($user)
            ->get(route('projects.files.download', $file))
            ->assertOk()
            ->assertHeader('content-disposition', "attachment; filename=spec.pdf");
    }

    public function test_uploader_can_delete_their_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $project = Project::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('projects.files.store', $project), [
            'file' => UploadedFile::fake()->create('spec.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $file = ProjectFile::first();

        $this->actingAs($user)
            ->delete(route('projects.files.destroy', $file))
            ->assertRedirect();

        $this->assertSoftDeleted('project_files', ['id' => $file->id]);
        Storage::disk($file->disk)->assertMissing($file->path);
    }
}
