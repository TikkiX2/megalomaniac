<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inspiration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inspiration\StoreSourceAuthRequest;
use App\Inspiration\Auth\InspirationAuthStore;
use App\Inspiration\Exceptions\SourceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Connect/disconnect a user session credential for one source (session cookie
 * pasted from the user's browser, or a simulated login as fallback). Credentials
 * are stored encrypted and never leave the server.
 */
class SourceAuthController extends Controller
{
    public function __construct(
        private readonly InspirationAuthStore $auth,
    ) {}

    public function store(StoreSourceAuthRequest $request, string $source): JsonResponse
    {
        $user = $request->user();

        if (! $this->auth->isSupported($source)) {
            return response()->json(['ok' => false, 'message' => "La fuente {$source} no acepta credenciales de sesión."], 422);
        }

        try {
            if ($request->validated('method') === 'cookie') {
                $this->auth->saveCookie($user, $source, $request->validated('cookie'));
            } else {
                $this->auth->saveLogin($user, $source, $request->validated('email'), $request->validated('password'));
            }
        } catch (SourceException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request, string $source): JsonResponse
    {
        $this->auth->disconnect($request->user(), $source);

        return response()->json(null, 204);
    }
}
