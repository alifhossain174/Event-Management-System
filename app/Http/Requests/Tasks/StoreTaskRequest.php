<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->route('event')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'due_at_local' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
        ];
    }

    public function taskAttributes(): array
    {
        $data = $this->validated();
        $data['due_at'] = filled($data['due_at_local'] ?? null)
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['due_at_local'], $this->route('event')->timezone)->utc()
            : null;
        unset($data['due_at_local']);

        return $data;
    }
}
