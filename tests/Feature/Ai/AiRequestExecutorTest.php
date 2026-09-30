<?php

use App\Ai\Support\AiAllProvidersFailedException;
use App\Ai\Support\AiHealthService;
use App\Ai\Support\AiProviderConfigurator;
use App\Ai\Support\AiProviderErrors;
use App\Ai\Support\AiRequestExecutor;
use App\Ai\Support\AiResolution;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderConnectionException;

/**
 * `Http::response()` returns a promise, so the failed client response is built
 * from its PSR-7 counterpart to get a real `Illuminate\Http\Client\Response`.
 */
function failedResponse(int $status, string $message): RequestException
{
    return new RequestException(new Response(Http::psr7Response(
        ['error' => ['message' => $message]],
        $status,
    )));
}

test('tries the next provider when the first fails with a fallbackable error', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $tried = [];
    $wired = [];

    $result = (new AiRequestExecutor)->execute($user, $resolution, function (string $key, string $model, AiProvider $provider) use (&$tried, &$wired, $a) {
        $tried[] = $provider->id;
        $wired[$key] = [$model, config('ai.providers.'.$key)];

        if ($provider->id === $a->id) {
            throw failedResponse(401, 'Unauthorized');
        }

        return 'ok-from-'.$provider->id;
    });

    expect($tried)->toBe([$a->id, $b->id])
        ->and($result)->toBe('ok-from-'.$b->id)
        ->and($wired)->toHaveKeys(['pm'.$a->id, 'pm'.$b->id])
        // Each attempt re-wires the config with its own credentials.
        ->and($wired['pm'.$b->id][0])->toBe($b->model)
        ->and($wired['pm'.$b->id][1]['url'])->toBe($b->url)
        ->and($wired['pm'.$b->id][1]['key'])->toBe($b->key)
        ->and((new AiHealthService)->statusFor($a)->consecutive_failures)->toBe(1)
        // The provider that answered closes its own circuit.
        ->and((new AiHealthService)->statusFor($b)->consecutive_failures)->toBe(0);
});

test('exhausted chain throws AiAllProvidersFailedException keeping the last error as previous', function () {
    $user = User::factory()->create();
    [$a] = AiProvider::factory()->count(1)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a]), null);

    $thrown = null;

    try {
        (new AiRequestExecutor)->execute($user, $resolution, function () {
            throw failedResponse(401, 'Unauthorized');
        });
    } catch (AiAllProvidersFailedException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(AiAllProvidersFailedException::class)
        ->and($thrown->lastException())->toBeInstanceOf(RequestException::class)
        ->and($thrown->getPrevious())->toBe($thrown->lastException())
        ->and($thrown->errors())->toBe([$a->name => $thrown->lastException()->getMessage()]);
});

test('non-fallbackable errors surface immediately without consuming the chain', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $tried = [];

    // A regular closure (not an arrow fn) so `&$tried` binds the test's array.
    $run = function () use ($user, $resolution, &$tried) {
        return (new AiRequestExecutor)->execute($user, $resolution, function ($k, $m, $p) use (&$tried) {
            $tried[] = $p->id;
            throw failedResponse(404, 'nope');
        });
    };

    expect($run)->toThrow(RequestException::class);

    expect($tried)->toBe([$a->id]); // 404 no consume la cadena
});

test('an empty chain fails with the no-provider copy', function () {
    $user = User::factory()->create();
    $resolution = new AiResolution(collect(), null);

    try {
        (new AiRequestExecutor)->execute($user, $resolution, fn () => throw new RuntimeException('never'));
    } catch (AiAllProvidersFailedException $e) {
        expect($e->errors())->toBe(['' => 'Sin proveedores disponibles.'])
            ->and($e->lastException())->toBeInstanceOf(RuntimeException::class);

        return;
    }

    $this->fail('The empty chain should raise AiAllProvidersFailedException.');
});

test('opencode endpoints get a stable session header, others do not', function () {
    $user = User::factory()->create();
    $openCode = AiProvider::factory()->for($user)->create(['url' => 'https://opencode.ai/zen/v1']);
    $other = AiProvider::factory()->for($user)->create(['url' => 'https://api.example.com/v1']);

    $key = AiProviderConfigurator::wire($openCode, 'thread-9');
    expect(config("ai.providers.$key.headers"))->toBe([
        'User-Agent' => 'megalomaniac-pro/1.0',
        'x-opencode-session' => 'thread-9',
    ]);

    // No conversation (one-shot features) falls back to a per-user id.
    $key = AiProviderConfigurator::wire($openCode->fresh());
    expect(config("ai.providers.$key.headers")['x-opencode-session'])->toBe('user-'.$user->id);

    $key = AiProviderConfigurator::wire($other);
    expect(config("ai.providers.$key.headers"))->toBe(['User-Agent' => 'megalomaniac-pro/1.0']);
});

test('only provider-shaped failures are fallbackable', function () {
    $errors = new AiProviderErrors;

    $status = fn (int $code) => new RequestException(new Response(Http::psr7Response([], $code)));

    foreach ([400, 401, 402, 403, 408, 429, 500, 503] as $code) {
        expect($errors->isFallbackable($status($code)))->toBeTrue("HTTP {$code} should fall back");
    }

    foreach ([404, 418, 422] as $code) {
        expect($errors->isFallbackable($status($code)))->toBeFalse("HTTP {$code} should surface");
    }

    $connection = new ConnectionException('cURL error 28: Operation timed out');
    expect($errors->isFallbackable($connection))->toBeTrue()
        // laravel/ai wraps the client failure one level deeper.
        ->and($errors->isFallbackable(ProviderConnectionException::forProvider('pm1', 0, $connection)))->toBeTrue()
        ->and($errors->isFallbackable(new RuntimeException('app bug')))->toBeFalse();
});
