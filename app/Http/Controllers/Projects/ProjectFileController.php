<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    /** 25 MB cap per file. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    public const ALLOWED_MIME = [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/markdown',
        'text/csv',
        'application/zip',
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectFile::class);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(self::MAX_BYTES / 1024),
            ],
        ]);

        $upload = $request->file('file');

        abort_unless(
            in_array($upload->getMimeType(), self::ALLOWED_MIME, true),
            422,
            'That file type is not allowed.',
        );

        $disk = 'local';
        $path = $upload->store("projects/{$project->id}", $disk);

        ProjectFile::create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $upload->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $upload->getMimeType(),
            'size_bytes' => $upload->getSize(),
        ]);

        return back()->with('flash.success', 'File uploaded.');
    }

    public function download(ProjectFile $file): StreamedResponse
    {
        $this->authorize('view', $file);

        abort_unless(
            Storage::disk($file->disk)->exists($file->path),
            404,
            'File no longer available.',
        );

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroy(ProjectFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);

        $file->deleteFromStorage();
        $file->delete();

        return back()->with('flash.success', 'File removed.');
    }
}
