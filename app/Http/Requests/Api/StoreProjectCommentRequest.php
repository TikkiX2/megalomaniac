<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required'],
            'parent_id' => ['nullable', 'exists:project_comments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'The comment content is required.',
        ];
    }
}
