<?php

namespace App\Http\Requests\Agents;

use App\Ai\Agents\AgentScheduler;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'max:5000'],
            'schedule_type' => ['required', Rule::in(['interval', 'cron'])],
            'schedule_value' => ['required', 'string', 'max:50'],
            'tools_policy' => ['nullable', 'array'],
            'tools_policy.internal' => ['nullable', 'array'],
            'tools_policy.integrations' => ['nullable', 'array'],
            'max_runs_per_day' => ['nullable', 'integer', 'min:1', 'max:288'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('schedule_type') === 'interval' && ! in_array($this->input('schedule_value'), array_keys(AgentScheduler::INTERVALS), true)) {
            $this->merge(['schedule_value' => '1h']);
        }
    }
}
