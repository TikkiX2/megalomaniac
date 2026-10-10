<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ChooseTomorrowNotification;
use Illuminate\Console\Command;

class TodayNotify extends Command
{
    protected $signature = 'today:notify';

    protected $description = 'Ritual 21:00: recordatorio neutro para elegir mañana (una sola vez, sin culpa).';

    public function handle(): int
    {
        User::query()->each(fn (User $user) => $user->notify(new ChooseTomorrowNotification));

        $this->info('Aviso neutro enviado (una sola vez).');

        return self::SUCCESS;
    }
}
