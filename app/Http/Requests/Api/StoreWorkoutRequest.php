<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'routine_id' => [
                'nullable',
                Rule::exists('routines', 'id')->where('user_id', $this->user()->id),
            ],
            'started_at' => [
                'date',
                Rule::requiredIf(fn () => ! $this->has('routine_id')
                    && ! $this->user()?->workouts()->whereNull('ended_at')->exists()),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'routine_id.exists' => 'The selected routine is invalid.',
            'started_at.required' => 'The start time is required.',
            'started_at.date' => 'The start time must be a valid date.',
        ];
    }
}
