<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Todo;
use App\Services\AI\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExtractActionItemsController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting, Provider $ai): RedirectResponse
    {
        $user = $request->user();
        abort_unless($meeting->hasAttendee($user), 403);

        $notes = trim((string) ($request->input('notes') ?? $meeting->description ?? ''));
        if ($notes === '') {
            return back()->withErrors(['notes' => 'No notes to summarize.']);
        }

        $response = $ai->complete([
            ['role' => 'system', 'content' => 'Extract action items from meeting notes. Return one action per line, no numbering, no extra prose. Each line must be an imperative sentence under 80 chars.'],
            ['role' => 'user', 'content' => $notes],
        ]);

        $items = collect(preg_split('/\r?\n/', $response))
            ->map(fn ($l) => trim(ltrim($l, "-*•0123456789. ")))
            ->filter(fn ($l) => $l !== '' && mb_strlen($l) <= 200)
            ->take(20);

        $created = 0;
        foreach ($items as $title) {
            Todo::create([
                'workspace_id' => $meeting->workspace_id,
                'user_id' => $user->id,
                'title' => $title,
                'category' => 'work',
            ]);
            $created++;
        }

        return back()->with('status', "Extracted {$created} action item(s).");
    }
}
