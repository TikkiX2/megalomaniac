<?php

namespace App\Http\Requests\Agents;

use App\Ai\Agents\AgentScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instructions' => ['sometimes', 'string', 'max:5000'],
            'schedule_type' => ['sometimes', Rule::in(['interval', 'cron'])],
            'schedule_value' => ['sometimes', 'string', 'max:50'],
            'tools_policy' => ['sometimes', 'array'],
            'max_runs_per_day' => ['sometimes', 'integer', 'min:1', 'max:288'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('schedule_type') === 'interval' && $this->has('schedule_value') && ! in_array($this->input('schedule_value'), array_keys(AgentScheduler::INTERVALS), true)) {
            $this->merge(['schedule_value' => '1h']);
        }
    }
}
