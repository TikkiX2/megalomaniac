<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:20000'],
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['nullable', 'string', 'max:32'],
            'is_private' => ['boolean'],
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
