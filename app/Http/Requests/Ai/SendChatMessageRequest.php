<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'thread_id' => ['nullable', 'string', 'size:36'],
            'model' => ['nullable', 'string', 'max:100'],
            'agent' => ['nullable', 'string', 'max:50'],
            'tools_policy' => ['nullable', 'array'],
            'tools_policy.mode' => ['required_with:tools_policy', 'string', 'in:auto,manual'],
            'tools_policy.groups' => ['nullable', 'array'],
            'tools_policy.groups.*' => ['string', 'max:30'],
            'attachment_ids' => ['nullable', 'array', 'max:5'],
            'attachment_ids.*' => [
                'string',
                'size:36',
                Rule::exists('chat_attachments', 'id')
                    ->where('user_id', $this->user()?->getKey())
                    ->where('kind', 'image')
                    ->where('status', 'ready'),
            ],
        ];
    }
}
