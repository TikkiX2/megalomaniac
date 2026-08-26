<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    /**
     * Show the AI settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/ai', [
            'ai' => [
                'ai_provider_url' => $request->user()->ai_provider_url,
                'ai_provider_key' => $request->user()->ai_provider_key,
                'ai_model' => $request->user()->ai_model,
                'ai_enabled' => $request->user()->ai_enabled,
            ],
        ]);
    }

    /**
     * Update the user's AI settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'ai_provider_url' => ['nullable', 'string', 'max:500'],
            'ai_provider_key' => ['nullable', 'string', 'max:500'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'ai_enabled' => ['required', 'boolean'],
        ])->validate();

        $request->user()->update($validated);

        return to_route('ai-settings.edit');
    }
}
