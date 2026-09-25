<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\OAuth\OAuthBroker;
use App\Models\Connection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class OAuthController extends Controller
{
    public function redirect(Request $request, int $connection, OAuthBroker $broker): RedirectResponse
    {
        $model = $this->owned($request, $connection);

        abort_unless($model->auth_type === AuthType::OAuth2, 422, 'La conexión no usa OAuth.');

        return redirect()->away($broker->redirectUrl($model));
    }

    public function callback(Request $request, int $connection, OAuthBroker $broker): RedirectResponse
    {
        $model = $this->owned($request, $connection);

        if ($request->filled('error')) {
            return to_route('connections.index')->with('error', 'Autorización cancelada.');
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $broker->handleCallback($model, $validated['code'], $validated['state']);

            return to_route('connections.index')->with('success', 'Cuenta conectada.');
        } catch (Throwable $e) {
            $model->forceFill([
                'status' => ConnectionStatus::Expired,
                'status_message' => Str::limit($e->getMessage(), 500),
            ])->save();

            return to_route('connections.index')->with('error', 'No se pudo completar la conexión OAuth.');
        }
    }

    protected function owned(Request $request, int $connectionId): Connection
    {
        return Connection::query()
            ->forUser($request->user())
            ->findOrFail($connectionId);
    }
}
