<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentWorkspace() !== null;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->currentWorkspace()->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('workspace_id', $workspaceId)],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:512'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'meeting_type' => ['nullable', Rule::in(['physical', 'online', 'hybrid'])],
            'channel' => ['nullable', 'string', 'max:32'],
            'reminder_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'attendee_ids' => ['nullable', 'array'],
            'attendee_ids.*' => [Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
        ];
    }
}
