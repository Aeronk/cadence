<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'fire_at' => ['required', 'date'],
            'subject_type' => ['nullable', 'string', 'max:128'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        Reminder::create($data + ['user_id' => $request->user()->id]);

        return back()->with('flash.success', 'Reminder set.');
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === request()->user()->id, 403);
        $reminder->delete();

        return back()->with('flash.success', 'Reminder removed.');
    }
}
