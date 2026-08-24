<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectPayment;
use App\Models\ProjectTask;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Seeder;

class FreelanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();
        $currency = Currency::where('code', 'ARS')->first() ?? Currency::factory()->create(['code' => 'ARS', 'symbol' => '$']);

        // Create 3 Clients
        Client::factory()->count(3)->create([
            'user_id' => $user->id,
        ])->each(function ($client) use ($user, $currency) {
            // Each client has 2 projects
            Project::factory()->count(2)->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'currency_id' => $currency->id,
            ])->each(function ($project) use ($user, $currency) {
                // Each project has 5 tasks
                ProjectTask::factory()->count(5)->create([
                    'project_id' => $project->id,
                ]);

                // Each project has 2 payments
                ProjectPayment::factory()->count(2)->create([
                    'project_id' => $project->id,
                    'currency_id' => $currency->id,
                    'status' => 'received',
                ]);

                // Update paid amount
                $project->updatePaidAmount();

                // Each project has 3 comments
                ProjectComment::factory()->count(3)->create([
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                ]);
            });

            // Each client has 1-2 quotes
            Quote::factory()->count(rand(1, 2))->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'currency_id' => $currency->id,
            ]);
        });
    }
}
