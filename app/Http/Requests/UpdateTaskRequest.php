<?php

namespace App\Http\Requests;

use App\Enums\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = $this->route('task')->workspace_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status_id' => ['nullable', Rule::exists('statuses', 'id')->where('workspace_id', $workspaceId)],
            'priority_id' => ['nullable', Rule::exists('priorities', 'id')->where('workspace_id', $workspaceId)],
            'milestone_id' => ['nullable', Rule::exists('milestones', 'id')->where('workspace_id', $workspaceId)],
            // Travel is a personal record, so a task may only be pinned to a
            // trip the person making the change owns.
            'trip_id' => [
                'nullable',
                Rule::exists('trips', 'id')
                    ->where('workspace_id', $workspaceId)
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'category' => ['nullable', Rule::in(Category::values())],
            'recurrence_rule' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_ends_on' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'position' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [Rule::exists('tags', 'id')->where('workspace_id', $workspaceId)],
        ];
    }

    /**
     * Select inputs post an empty string for "none". Treat those as null so the
     * nullable rules below accept them instead of failing an `in` check.
     */
    protected function prepareForValidation(): void
    {
        $nullable = [
            'status_id', 'priority_id', 'milestone_id', 'trip_id', 'category',
            'recurrence_rule', 'recurrence_ends_on', 'start_date', 'due_date',
        ];

        $this->merge(collect($this->only($nullable))
            ->filter(fn ($value) => $value === '')
            ->map(fn () => null)
            ->all());
    }
}
