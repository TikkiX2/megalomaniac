<?php

namespace App\Integrations\Transports;

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Enums\AuthType;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class DirectTransport implements Transport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        $started = microtime(true);

        try {
            $client = $this->client($connection, $call);

            if ($call->body !== null) {
                $client = $client->withBody($call->body, $call->contentType ?? 'application/json');
            }

            $response = $client->send($call->method, ltrim($call->path, '/'), array_filter([
                'query' => $call->query,
                'json' => $call->json,
                'headers' => $call->headers,
            ], fn (mixed $value): bool => $value !== null && $value !== []));

            $json = $response->json();

            return new HttpResult(
                ok: $response->successful(),
                status: $response->status(),
                data: is_array($json) ? $json : null,
                body: $response->body(),
                error: $response->successful() ? null : $this->errorMessage($response->status(), $json),
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        } catch (Throwable $e) {
            return new HttpResult(
                ok: false,
                status: 0,
                data: null,
                body: '',
                error: $e->getMessage(),
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        }
    }

    public function exec(Connection $connection, string $command): ExecResult
    {
        throw UnsupportedTransportException::for('exec', 'direct');
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('direct transport ready');
    }

    protected function client(Connection $connection, HttpCall $call): PendingRequest
    {
        $request = Http::timeout($call->timeout ?? (int) config('integrations.http.timeout'))
            ->acceptJson();

        if (filled($connection->base_url)) {
            $request = $request->baseUrl(rtrim((string) $connection->base_url, '/'));
        }

        $headers = array_merge($connection->options['headers'] ?? [], $call->headers);

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $credentials = $connection->credentials ?? [];

        return match ($connection->auth_type) {
            AuthType::Basic => $request->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            ),
            AuthType::None => $request,
            AuthType::OAuth2 => $request->withToken((string) ($credentials['access_token'] ?? '')),
            default => $request->withToken((string) ($credentials['token'] ?? $credentials['access_token'] ?? '')),
        };
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    protected function errorMessage(int $status, mixed $json): string
    {
        $message = is_array($json) ? ($json['message'] ?? $json['error'] ?? null) : null;

        return $message ? "HTTP {$status} — {$message}" : "HTTP {$status}";
    }
}
