<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by route policy binding.
    }

    public function rules(): array
    {
        $workspaceId = $this->route('project')->workspace_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['nullable', Rule::exists('statuses', 'id')->where('workspace_id', $workspaceId)],
            'priority_id' => ['nullable', Rule::exists('priorities', 'id')->where('workspace_id', $workspaceId)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [
                Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId),
            ],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
        ];
    }
}
