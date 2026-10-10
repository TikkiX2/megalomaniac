<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TodayClose extends Command
{
    protected $signature = 'today:close';

    protected $description = 'Cierre 00:05: los pendientes quedan como están, sin mover ni notificar.';

    public function handle(): int
    {
        // Intencionalmente sin mutación (B4): los pending quedan pending.
        $this->info('Día cerrado. Sin cambios en los pendientes.');

        return self::SUCCESS;
    }
}
