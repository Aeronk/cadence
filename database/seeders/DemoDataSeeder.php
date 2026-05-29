<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('email', env('SUPERADMIN_EMAIL', 'admin@cadence.test'));

        if (! $admin) {
            $this->command->warn('Super admin not found — run SuperAdminSeeder first.');

            return;
        }

        $alice = User::firstOrCreate(
            ['email' => 'alice@cadence.test'],
            ['name' => 'Alice Member', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $bob = User::firstOrCreate(
            ['email' => 'bob@cadence.test'],
            ['name' => 'Bob Member', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        $workspace = Workspace::firstWhere(['owner_id' => $admin->id, 'slug' => 'cadence-hq'])
            ?: Workspace::create([
                'owner_id' => $admin->id,
                'name' => 'Cadence HQ',
                'is_personal' => false,
                'description' => 'Demo workspace seeded for clicking around.',
            ]);

        $workspace->members()->syncWithoutDetaching([
            $alice->id => ['role' => WorkspaceRole::Admin->value, 'joined_at' => now()],
            $bob->id => ['role' => WorkspaceRole::Member->value, 'joined_at' => now()],
        ]);

        $tags = collect(['Frontend', 'Backend', 'Design', 'Bug', 'Customer'])
            ->map(fn ($name) => Tag::firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => str()->slug($name)],
                ['name' => $name, 'color' => fake()->randomElement(['blue', 'green', 'orange', 'red', 'purple'])],
            ));

        $statusInProgress = $workspace->statuses()->firstWhere('slug', 'in-progress');
        $statusDone = $workspace->statuses()->firstWhere('slug', 'done');
        $priorityHigh = $workspace->priorities()->firstWhere('slug', 'high');
        $priorityMedium = $workspace->priorities()->firstWhere('slug', 'medium');

        $launch = $this->ensureProject($workspace, $admin, 'Q3 Product Launch', 'Ship the v1 marketing site and onboarding flow.', $statusInProgress, $priorityHigh);
        $launch->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner'], $alice->id => ['role' => 'member']]);
        $launch->tags()->syncWithoutDetaching($tags->whereIn('slug', ['frontend', 'design'])->pluck('id'));

        $infra = $this->ensureProject($workspace, $alice, 'Infrastructure Hardening', 'Move queue to Redis, add staging environment, set up CI.', $statusInProgress, $priorityMedium);
        $infra->members()->syncWithoutDetaching([$alice->id => ['role' => 'owner'], $bob->id => ['role' => 'member']]);
        $infra->tags()->syncWithoutDetaching($tags->whereIn('slug', ['backend'])->pluck('id'));

        $launchTasks = [
            ['title' => 'Write landing page copy', 'creator' => $admin, 'status' => $statusDone, 'completed' => true],
            ['title' => 'Design hero illustration', 'creator' => $alice, 'status' => $statusInProgress, 'priority' => $priorityHigh],
            ['title' => 'Build pricing component', 'creator' => $alice, 'status' => $statusInProgress],
            ['title' => 'QA onboarding flow', 'creator' => $admin, 'priority' => $priorityHigh],
        ];

        foreach ($launchTasks as $i => $data) {
            $task = Task::firstOrCreate(
                ['project_id' => $launch->id, 'title' => $data['title']],
                [
                    'workspace_id' => $workspace->id,
                    'created_by' => $data['creator']->id,
                    'status_id' => $data['status']->id ?? null,
                    'priority_id' => $data['priority']->id ?? null,
                    'position' => $i,
                    'completed_at' => ! empty($data['completed']) ? now()->subDay() : null,
                    'due_date' => now()->addDays(7 + $i),
                ],
            );

            $task->assignees()->syncWithoutDetaching([$data['creator']->id]);
        }

        $parent = Task::firstWhere(['project_id' => $infra->id, 'title' => 'Move queue to Redis'])
            ?: Task::create([
                'workspace_id' => $workspace->id,
                'project_id' => $infra->id,
                'created_by' => $alice->id,
                'title' => 'Move queue to Redis',
                'status_id' => $statusInProgress->id ?? null,
                'priority_id' => $priorityHigh->id ?? null,
                'position' => 0,
                'due_date' => now()->addWeeks(2),
            ]);
        $parent->assignees()->syncWithoutDetaching([$bob->id]);

        foreach (['Provision Redis instance', 'Migrate jobs from database driver', 'Add Horizon for monitoring'] as $i => $title) {
            Task::firstOrCreate(
                ['project_id' => $infra->id, 'parent_id' => $parent->id, 'title' => $title],
                [
                    'workspace_id' => $workspace->id,
                    'created_by' => $bob->id,
                    'position' => $i,
                ],
            );
        }

        foreach (['Reply to investor email', 'Draft team weekly update', 'Review pull requests'] as $title) {
            Todo::firstOrCreate(
                ['user_id' => $admin->id, 'title' => $title],
                ['workspace_id' => $workspace->id, 'priority' => 'medium'],
            );
        }

        Note::firstOrCreate(
            ['user_id' => $admin->id, 'workspace_id' => $workspace->id, 'title' => 'Launch checklist'],
            ['body' => "- DNS cutover at 09:00 UTC\n- Verify Stripe webhooks\n- Post launch tweet\n- Monitor /up endpoint", 'color' => 'yellow', 'is_pinned' => true],
        );
        Note::firstOrCreate(
            ['user_id' => $admin->id, 'workspace_id' => $workspace->id, 'title' => 'Hiring pipeline'],
            ['body' => "Need to schedule second-round interviews for the senior backend role.", 'color' => 'blue'],
        );

        $kickoff = Meeting::firstOrCreate(
            ['workspace_id' => $workspace->id, 'host_id' => $admin->id, 'title' => 'Weekly kickoff'],
            [
                'starts_at' => now()->next('Monday')->setTime(9, 0),
                'ends_at' => now()->next('Monday')->setTime(9, 30),
                'description' => 'Set goals for the week, review blockers.',
                'meeting_url' => 'https://meet.example.com/cadence-kickoff',
            ],
        );
        $kickoff->attendees()->syncWithoutDetaching([
            $admin->id => ['rsvp_status' => 'accepted'],
            $alice->id => ['rsvp_status' => 'accepted'],
            $bob->id => ['rsvp_status' => 'pending'],
        ]);

        $this->command->info('Demo data seeded into "Cadence HQ" workspace.');
        $this->command->info('  → alice@cadence.test / password (admin)');
        $this->command->info('  → bob@cadence.test / password (member)');
    }

    protected function ensureProject(Workspace $workspace, User $creator, string $title, ?string $description, ?object $status, ?object $priority): Project
    {
        return Project::firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => $title],
            [
                'created_by' => $creator->id,
                'description' => $description,
                'status_id' => $status->id ?? null,
                'priority_id' => $priority->id ?? null,
                'start_date' => now()->subWeek(),
                'due_date' => now()->addMonth(),
            ],
        );
    }
}
