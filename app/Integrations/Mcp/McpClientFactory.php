<?php

namespace App\Integrations\Mcp;

use App\Models\Connection;
use Laravel\Mcp\Client\Transport\HttpTransport;
use Laravel\Mcp\WebClient;

class McpClientFactory
{
    public function base(Connection $connection): WebClient
    {
        $transport = new HttpTransport((string) $connection->base_url);
        $transport->setTimeoutSeconds((float) ($connection->options['timeout'] ?? 20));

        $client = new WebClient($transport);

        $headers = (array) ($connection->options['headers'] ?? []);

        if ($headers !== []) {
            $client->withHeaders($headers);
        }

        return $client;
    }

    public function for(Connection $connection): WebClient
    {
        $client = $this->base($connection);

        $credentials = $connection->credentials ?? [];
        $token = (string) ($credentials['token'] ?? $credentials['access_token'] ?? '');

        if ($token !== '') {
            $client->withToken($token);
        }

        return $client;
    }
}
