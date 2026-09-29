<?php

namespace App\Http\Requests\Api;

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['required', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
