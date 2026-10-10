<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HoyAvisar extends Command
{
    protected $signature = 'hoy:avisar';

    protected $description = 'Ritual 21:00: recordatorio neutro para elegir mañana (una sola, sin culpa).';

    public function handle(): int
    {
        $this->info('¿Elegimos las 3 de mañana? Te lleva un minuto.');

        return self::SUCCESS;
    }
}
