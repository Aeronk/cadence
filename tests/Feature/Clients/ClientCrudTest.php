<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_client(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('clients.store'), [
                'name' => 'Acme Corp',
                'email' => 'hello@acme.example',
                'company' => 'Acme Corp Ltd',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'workspace_id' => $user->currentWorkspace()->id,
            'name' => 'Acme Corp',
            'email' => 'hello@acme.example',
            'created_by' => $user->id,
        ]);
    }

    public function test_clients_are_scoped_to_current_workspace(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Client::factory()->for($alice->currentWorkspace())->count(2)->create(['created_by' => $alice->id]);
        Client::factory()->for($bob->currentWorkspace())->create(['created_by' => $bob->id]);

        $this->actingAs($alice)->get(route('clients.index'))
            ->assertInertia(fn ($page) => $page->component('Clients/Index')->has('clients', 2));
    }

    public function test_outsider_cannot_update_client(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $client = Client::factory()->for($alice->currentWorkspace())->create(['created_by' => $alice->id]);

        $this->actingAs($bob)
            ->patch(route('clients.update', $client), ['name' => 'pwned'])
            ->assertForbidden();
    }

    public function test_project_can_be_linked_to_clients_on_store(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->for($user->currentWorkspace())->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'title' => 'Acme launch',
                'client_ids' => [$client->id],
            ])
            ->assertRedirect();

        $project = Project::firstWhere('title', 'Acme launch');
        $this->assertTrue($project->clients->contains($client));
    }
}
