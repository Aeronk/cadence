<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => [Rule::exists('clients', 'id')->where('workspace_id', $workspaceId)],
        ];
    }
}
