<?php

namespace App\Http\Requests\Feed;

use App\Models\Connection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedSourceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['rss', 'hackernews', 'reddit', 'youtube'])],
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
            'config.url' => ['required_if:kind,rss', 'nullable', 'url', 'max:1000'],
            'config.subreddit' => ['required_if:kind,reddit', 'nullable', 'string', 'max:100'],
            'config.sort' => ['nullable', Rule::in(['hot', 'new', 'top'])],
            'config.channel_ids' => ['required_if:kind,youtube', 'nullable', 'array'],
            'connection_id' => [
                'nullable',
                Rule::exists('connections', 'id')->where('user_id', $this->user()->id),
            ],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('kind') === 'hackernews' && ! $this->has('config.url')) {
            $this->merge(['config' => array_merge($this->input('config', []), [
                'url' => 'https://news.ycombinator.com/rss',
            ])]);
        }
    }

    public function connection(): ?Connection
    {
        $id = $this->validated('connection_id');

        return $id ? Connection::query()->find($id) : null;
    }
}
