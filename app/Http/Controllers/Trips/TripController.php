<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripChecklistItem;
use App\Models\TripSegment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Trip::class);

        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $trips = Trip::query()
            ->forWorkspace($workspace)
            ->where('user_id', $user->id)
            ->orderByDesc('departs_at')
            ->get(['id', 'name', 'purpose', 'destination_country', 'destination_city',
                'departs_at', 'returns_at', 'status']);

        return Inertia::render('Trips/Index', ['trips' => $trips]);
    }

    public function show(Trip $trip): Response
    {
        $this->authorize('view', $trip);

        return Inertia::render('Trips/Show', [
            'trip' => $trip,
            'segments' => $trip->segments,
            'checklist' => $trip->checklist,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Trip::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:64'],
            'destination_country' => ['nullable', 'string', 'size:2'],
            'destination_city' => ['nullable', 'string', 'max:128'],
            'departs_at' => ['required', 'date'],
            'returns_at' => ['required', 'date', 'after_or_equal:departs_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $trip = Trip::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'user_id' => $request->user()->id,
            'status' => Trip::STATUS_PLANNED,
        ]);

        return to_route('trips.show', $trip)->with('flash.success', 'Trip created.');
    }

    public function update(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:64'],
            'destination_country' => ['nullable', 'string', 'size:2'],
            'destination_city' => ['nullable', 'string', 'max:128'],
            'departs_at' => ['sometimes', 'required', 'date'],
            'returns_at' => ['sometimes', 'required', 'date', 'after_or_equal:departs_at'],
            'status' => ['nullable', Rule::in(['planned', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ]);

        $trip->fill($data)->save();

        return back()->with('flash.success', 'Trip updated.');
    }

    public function destroy(Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);
        $trip->delete();

        return to_route('trips.index')->with('flash.success', 'Trip removed.');
    }

    // ---- Segments ----

    public function storeSegment(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'type' => ['required', Rule::in(['flight', 'train', 'drive', 'hotel', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'from_location' => ['nullable', 'string', 'max:255'],
            'to_location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'details' => ['nullable', 'string'],
        ]);

        $trip->segments()->create($data);

        return back()->with('flash.success', 'Segment added.');
    }

    public function destroySegment(TripSegment $segment): RedirectResponse
    {
        $this->authorize('update', $segment->trip);
        $segment->delete();

        return back()->with('flash.success', 'Segment removed.');
    }

    // ---- Checklist ----

    public function storeChecklistItem(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $trip->checklist()->create($data + [
            'position' => (int) $trip->checklist()->max('position') + 1,
        ]);

        return back()->with('flash.success', 'Item added.');
    }

    public function toggleChecklistItem(TripChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->trip);
        $item->forceFill(['checked' => ! $item->checked])->save();

        return back();
    }

    public function destroyChecklistItem(TripChecklistItem $item): RedirectResponse
    {
        $this->authorize('update', $item->trip);
        $item->delete();

        return back()->with('flash.success', 'Item removed.');
    }
}
