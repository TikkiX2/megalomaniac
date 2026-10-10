<?php

namespace App\Http\Requests\Today;

use App\Models\QueueItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreQueueItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:'.implode(',', QueueItem::TYPES)],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'cover_url' => ['nullable', 'string', 'max:500'],
            'year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'creator' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipo inválido.',
        ];
    }
}
