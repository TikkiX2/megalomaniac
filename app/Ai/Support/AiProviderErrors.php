<?php

declare(strict_types=1);

namespace App\Ai\Support;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Decides whether a failed attempt justifies moving to the next provider.
 *
 * Fallbackable: quota/credit problems, overload, rate limits, connection and
 * timeout failures, and HTTP statuses that usually mean "this provider cannot
 * serve this request" (400 model rejected, 401/403 bad key, 402 out of
 * credits, 408 timeout, 429 rate limited) plus any 5xx.
 *
 * Everything else — a 404 on a wrong endpoint/model, a 418, or any application
 * exception — surfaces immediately instead of burning the rest of the chain.
 *
 * The whole `previous` chain is inspected because laravel/ai wraps the
 * low-level failure (`ProviderConnectionException` holding the
 * `Illuminate\Http\Client\ConnectionException`, which in turn holds Guzzle's
 * `ConnectException` for timeouts and refused connections).
 */
class AiProviderErrors
{
    /**
     * HTTP statuses that make another provider a better bet than a retry.
     *
     * @var list<int>
     */
    public const FALLBACK_STATUSES = [400, 401, 402, 403, 408, 429];

    public function isFallbackable(Throwable $e): bool
    {
        $chain = $this->chain($e);

        // A response means the provider answered: the status decides, so a 404
        // stays non-fallbackable even when wrapped in a Guzzle exception.
        $status = $this->statusOf($e);

        if ($status !== null) {
            return $status >= 500 || in_array($status, self::FALLBACK_STATUSES, true);
        }

        foreach ($chain as $throwable) {
            if ($throwable instanceof InsufficientCreditsException
                || $throwable instanceof RateLimitedException
                || $throwable instanceof ProviderOverloadedException
                || $throwable instanceof ProviderConnectionException
                || $throwable instanceof ConnectionException
                || $throwable instanceof GuzzleException) {
                return true;
            }
        }

        return false;
    }

    /**
     * The HTTP status carried by a failed request, or null when the failure
     * never produced a response (connection refused, timeout, app error).
     */
    public function statusOf(Throwable $e): ?int
    {
        foreach ($this->chain($e) as $throwable) {
            if ($throwable instanceof RequestException) {
                return $throwable->response?->status();
            }

            if ($throwable instanceof GuzzleRequestException) {
                return $throwable->getResponse()?->getStatusCode();
            }
        }

        return null;
    }

    /**
     * The exception and every exception it wraps.
     *
     * @return list<Throwable>
     */
    private function chain(Throwable $e): array
    {
        $chain = [];

        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $chain[] = $current;
        }

        return $chain;
    }
}
