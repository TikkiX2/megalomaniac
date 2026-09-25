<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'pinned' => ['sometimes', 'boolean'],
            'tools_policy' => ['sometimes', 'nullable', 'array'],
            'tools_policy.mode' => ['required_with:tools_policy', 'string', 'in:auto,manual'],
            'tools_policy.groups' => ['nullable', 'array'],
            'tools_policy.groups.*' => ['string', 'max:30'],
        ];
    }
}
