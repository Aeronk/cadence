<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectArchiveController extends Controller
{
    public function store(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $project->forceFill(['archived_at' => now()])->save();

        return back()->with('flash.success', 'Project archived.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $project->forceFill(['archived_at' => null])->save();

        return back()->with('flash.success', 'Project restored.');
    }
}
