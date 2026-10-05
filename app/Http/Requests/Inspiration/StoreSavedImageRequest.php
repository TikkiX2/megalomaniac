<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspiration;

use App\Inspiration\SourceManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a save request coming from the explore/search cards.
 *
 * `thumbnail_url` is intentionally not persisted; it is forwarded to the
 * thumbnail job so it can prefer the lighter remote asset.
 */
class StoreSavedImageRequest extends FormRequest
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
            'source' => ['required', 'string', Rule::in($this->allowedSources())],
            'source_id' => ['required', 'string', 'max:255'],
            'image_url' => ['required', 'string', 'max:2048'],
            'page_url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'author_url' => ['nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'license' => ['nullable', 'string', 'max:255'],
            'maturity' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:5000'],
            'moodboard_id' => ['nullable', 'integer', Rule::exists('moodboards', 'id')],
        ];
    }

    /**
     * The registered source keys, never a hardcoded config list.
     *
     * @return array<int, string>
     */
    private function allowedSources(): array
    {
        return app(SourceManager::class)->all()->keys()->all();
    }
}
