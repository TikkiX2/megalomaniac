<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\StoreMomentRequest;
use App\Http\Requests\Journal\UpdateMomentRequest;
use App\Models\JournalMoment;
use App\Services\Journal\JournalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MomentController extends Controller
{
    public function __construct(private readonly JournalService $journal) {}

    public function store(StoreMomentRequest $request, string $date): RedirectResponse
    {
        $this->guardDate($date);

        DB::transaction(function () use ($request, $date): void {
            $day = $this->journal->dayFor($request->user(), $date);

            $this->journal->createMoment($day, $request->validated());
        });

        return back()->with('success', 'Momento agregado.');
    }

    public function update(UpdateMomentRequest $request, JournalMoment $moment): RedirectResponse
    {
        $this->authorize('update', $moment);

        $this->journal->updateMoment($moment, $request->validated());

        return back()->with('success', 'Momento actualizado.');
    }

    public function destroy(JournalMoment $moment): RedirectResponse
    {
        $this->authorize('delete', $moment);

        $day = $moment->day;

        $moment->delete();

        if ($day !== null) {
            $this->journal->pruneIfEmpty($day);
        }

        return back()->with('success', 'Momento eliminado.');
    }

    private function guardDate(string $date): void
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $date) {
            abort(404);
        }
    }
}
