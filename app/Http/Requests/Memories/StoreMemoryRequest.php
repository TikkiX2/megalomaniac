<?php

namespace App\Http\Requests\Memories;

use App\Ai\Enums\MemoryScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:500'],
            'scope' => ['required', 'string', Rule::enum(MemoryScope::class)],
            'thread_id' => [
                'required_if:scope,thread',
                'nullable',
                'uuid',
                Rule::exists('agent_conversations', 'id')
                    ->where('participant_type', $this->user()->getMorphClass())
                    ->where('participant_id', $this->user()->getKey()),
            ],
        ];
    }
}
