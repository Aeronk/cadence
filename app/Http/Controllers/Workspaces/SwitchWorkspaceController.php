<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SwitchWorkspaceController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if (! $user->switchWorkspace($workspace)) {
            throw ValidationException::withMessages([
                'workspace' => 'You are not a member of that workspace.',
            ]);
        }

        return back();
    }
}
