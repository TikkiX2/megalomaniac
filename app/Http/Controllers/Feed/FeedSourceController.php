<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreFeedSourceRequest;
use App\Models\FeedSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedSourceController extends Controller
{
    public function store(StoreFeedSourceRequest $request): RedirectResponse
    {
        FeedSource::create([
            'user_id' => $request->user()->id,
            'kind' => $request->validated('kind'),
            'name' => $request->validated('name'),
            'config' => $request->validated('config'),
            'connection_id' => $request->validated('connection_id'),
            'enabled' => $request->validated('enabled') ?? true,
        ]);

        return to_route('feed.settings')->with('success', 'Fuente creada.');
    }

    public function update(Request $request, int $source): RedirectResponse
    {
        $model = $this->owned($request, $source);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'config' => ['sometimes', 'array'],
            'connection_id' => [
                'sometimes',
                'nullable',
                Rule::exists('connections', 'id')->where('user_id', $request->user()->id),
            ],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $model->update($validated);

        return back()->with('success', 'Fuente actualizada.');
    }

    public function destroy(Request $request, int $source): RedirectResponse
    {
        $this->owned($request, $source)->delete();

        return back()->with('success', 'Fuente eliminada.');
    }

    protected function owned(Request $request, int $sourceId): FeedSource
    {
        return FeedSource::query()
            ->forUser($request->user())
            ->findOrFail($sourceId);
    }
}
