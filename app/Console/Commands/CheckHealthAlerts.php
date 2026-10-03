<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Health\HealthAlertService;
use Illuminate\Console\Command;

class CheckHealthAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for health alerts and create notifications';

    /**
     * Execute the command.
     */
    public function handle()
    {
        // This command would typically be run for all users,
        // but for demonstration we'll just check the first user
        // In production, you'd want to iterate through all active users
        $user = User::first();

        if ($user) {
            HealthAlertService::checkForAlerts($user);
            $this->info('Health alerts checked for user ID: '.$user->id);
        } else {
            $this->info('No users found.');
        }

        return 0;
    }
}
