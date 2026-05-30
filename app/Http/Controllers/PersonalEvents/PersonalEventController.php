<?php

namespace App\Http\Controllers\PersonalEvents;

use App\Http\Controllers\Controller;
use App\Models\PersonalEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonalEventController extends Controller
{
    public function index(Request $request): Response
    {
        $events = PersonalEvent::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('event_date')
            ->get();

        return Inertia::render('PersonalEvents/Index', ['events' => $events]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(['birthday', 'anniversary', 'school', 'health', 'other'])],
            'event_date' => ['required', 'date'],
            'recurs_yearly' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        PersonalEvent::create($data + [
            'user_id' => $request->user()->id,
            'workspace_id' => $request->user()->currentWorkspace()?->id,
        ]);

        return back()->with('flash.success', 'Event added.');
    }

    public function destroy(PersonalEvent $event, Request $request): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);
        $event->delete();

        return back()->with('flash.success', 'Event removed.');
    }
}
