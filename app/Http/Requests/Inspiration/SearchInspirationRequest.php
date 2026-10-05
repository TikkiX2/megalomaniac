<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspiration;

use App\Inspiration\SourceManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchInspirationRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', Rule::in($this->allowedSources())],
        ];
    }

    /**
     * Every registered source key plus the `all` fan-out pseudo-source. The
     * list lives in the SourceManager registry, not in config.
     *
     * @return array<int, string>
     */
    private function allowedSources(): array
    {
        return array_merge(
            app(SourceManager::class)->all()->keys()->all(),
            ['all'],
        );
    }
}
