<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $workspace = $request->user()->currentWorkspace();

        return Inertia::render('Clients/Index', [
            'clients' => Client::query()
                ->forWorkspace($workspace)
                ->withCount('projects')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'url', 'max:512'],
            'notes' => ['nullable', 'string'],
        ]);

        Client::create($data + [
            'workspace_id' => $request->user()->currentWorkspace()->id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash.success', 'Client added.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'url', 'max:512'],
            'notes' => ['nullable', 'string'],
        ]);

        $client->fill($data)->save();

        return back()->with('flash.success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();

        return back()->with('flash.success', 'Client removed.');
    }
}
