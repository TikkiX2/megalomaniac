<?php

namespace App\Http\Requests\Settings;

use App\Ai\Enums\AiScope;
use App\Ai\Support\AiPromptComposer;
use App\Models\AiProvider;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiScopeRequest extends FormRequest
{
    /**
     * The scope lives in the URL (`settings/ai/scopes/{scope}`), so it is merged
     * into the payload to be validated like any other field.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['scope' => $this->route('scope')]);
    }

    /**
     * `provider_chain` is stored as an empty array (not deleted) when the user
     * clears it: an empty chain means "inherit", and keeping the row lets the
     * prompt layer of that scope live in the same place.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(AiScope::values())],
            'provider_chain' => ['sometimes', 'array', 'max:20'],
            'provider_chain.*' => ['integer', Rule::exists('ai_providers', 'id')],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:'.AiPromptComposer::MAX_CHARS],
        ];
    }

    /**
     * Ownership check for the chain. The error is attached to `provider_chain`
     * (not `provider_chain.N`) so the settings form can show it as a single
     * message under the chain control.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $chain = $this->input('provider_chain');

                if (! is_array($chain) || $chain === []) {
                    return;
                }

                $ids = array_values(array_unique(array_map('intval', array_filter($chain, 'is_numeric'))));
                $owned = AiProvider::query()
                    ->where('user_id', $this->user()->getKey())
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->all();

                if (count($owned) !== count($ids)) {
                    $validator->errors()->add('provider_chain', 'Solo podés usar proveedores tuyos en la cadena.');
                }
            },
        ];
    }
}
