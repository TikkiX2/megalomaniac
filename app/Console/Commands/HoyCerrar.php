<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HoyCerrar extends Command
{
    protected $signature = 'hoy:cerrar';

    protected $description = 'Cierre 00:00: los pendientes quedan como están, sin mover ni notificar.';

    public function handle(): int
    {
        // Intencionalmente sin mutación (B4).

        return self::SUCCESS;
    }
}
