<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Services\Journal\JournalMemories;
use App\Services\Journal\JournalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly JournalMemories $memories,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = $this->journal->today($user);

        return Inertia::render('journal/Review', [
            'onThisDay' => $this->memories->onThisDay($user, $today)->all(),
            'random' => fn (): ?array => $this->memories->random($user, $today),
            'today' => $today->toDateString(),
        ]);
    }
}
