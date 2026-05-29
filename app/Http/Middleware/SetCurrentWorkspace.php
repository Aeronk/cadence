<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $workspace = $user->currentWorkspace();

            if ($workspace instanceof Workspace) {
                app()->instance('currentWorkspace', $workspace);
                $request->attributes->set('currentWorkspace', $workspace);
            }
        }

        return $next($request);
    }
}
