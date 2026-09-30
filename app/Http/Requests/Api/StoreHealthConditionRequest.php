<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ConditionKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
