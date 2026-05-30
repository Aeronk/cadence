<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AI\BriefingComposer;
use Illuminate\Console\Command;

class GenerateDailyBriefings extends Command
{
    protected $signature = 'briefings:generate';

    protected $description = 'Compose a daily briefing for each active user in each of their workspaces.';

    public function handle(BriefingComposer $composer): int
    {
        $count = 0;
        User::query()->whereNotNull('current_workspace_id')->chunkById(100, function ($users) use ($composer, &$count) {
            foreach ($users as $user) {
                $ws = $user->currentWorkspace();
                if (! $ws) continue;
                try {
                    $composer->composeFor($user, $ws);
                    $count++;
                } catch (\Throwable $e) {
                    $this->error("Briefing failed for {$user->id}: " . $e->getMessage());
                }
            }
        });

        $this->info("Generated {$count} briefing(s).");
        return self::SUCCESS;
    }
}
