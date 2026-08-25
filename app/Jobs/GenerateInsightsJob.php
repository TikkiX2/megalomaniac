<?php

namespace App\Jobs;

use App\Ai\Services\SuggestionService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public User $user,
    ) {}

    public function handle(SuggestionService $suggestionService): void
    {
        $suggestionService->generateSuggestions($this->user);
    }
}
