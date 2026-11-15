<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMomentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'body' => ['sometimes', 'required', 'string', 'max:20000'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:30'],
            'tags.*' => ['nullable', 'string', 'max:32'],
            'is_private' => ['sometimes', 'boolean'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * An empty tag input means "no tags" rather than a validation failure.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('tags') && in_array($this->input('tags'), [null, ''], true)) {
            $this->merge(['tags' => []]);
        }
    }
}
