<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use Illuminate\Foundation\Http\FormRequest;

class StoreMomentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['nullable', 'string', 'max:32'],
            'is_private' => ['boolean'],
            'occurred_at' => ['nullable', 'date'],
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
