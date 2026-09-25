<?php

namespace App\Ai\Agents;

use App\Integrations\ConnectorRegistry;
use App\Models\Connection;
use App\Models\User;

class TelegramNotifier
{
    public function send(User $user, string $text): bool
    {
        $connection = Connection::query()
            ->forUser($user)
            ->enabled()
            ->where('kind', 'telegram')
            ->first();

        if (! $connection) {
            return false;
        }

        $result = app(ConnectorRegistry::class)
            ->for('telegram')
            ->execute($connection, 'message.send', ['text' => $text]);

        return $result->ok;
    }
}
