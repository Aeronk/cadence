<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->currentWorkspace() !== null;
    }

    public function rules(): array
    {
        $workspaceId = $this->user()->currentWorkspace()->id;

        return [
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('workspace_id', $workspaceId),
            ],
            'parent_id' => ['nullable', Rule::exists('tasks', 'id')->where('workspace_id', $workspaceId)],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->where('workspace_id', $workspaceId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['nullable', Rule::exists('statuses', 'id')->where('workspace_id', $workspaceId)],
            'priority_id' => ['nullable', Rule::exists('priorities', 'id')->where('workspace_id', $workspaceId)],
            'category' => ['nullable', Rule::in(Category::values())],
            'recurrence_rule' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_ends_on' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
        ];
    }

    public function project(): Project
    {
        return Project::findOrFail($this->integer('project_id'));
    }
}
