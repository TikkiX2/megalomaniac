<?php

declare(strict_types=1);

namespace App\Services\Journal;

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\JournalReflection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class JournalReflectionService
{
    private const int PAYLOAD_TEXT_LIMIT = 7000;

    private const int FALLBACK_TEXT_LIMIT = 7000;

    private const int DAY_BODY_LIMIT = 500;

    private const int MOMENT_TEXT_LIMIT = 200;

    private const int FALLBACK_EXCERPT_LIMIT = 200;

    private const int MAX_TAGS = 3;

    private const int MAX_MOMENTS = 3;

    private const int RECAP_WEEKS = 8;

    public function __construct(
        private readonly JournalService $journal,
        private readonly JournalStats $stats,
    ) {}

    /**
     * Monday of the user's current local week.
     */
    public function weekStart(User $user): CarbonImmutable
    {
        return $this->journal->today($user)->startOfWeek(CarbonInterface::MONDAY);
    }

    public function forWeek(User $user, CarbonImmutable $weekStart): ?JournalReflection
    {
        return JournalReflection::query()
            ->where('user_id', $user->id)
            ->where('week_start', $weekStart->toDateString())
            ->first();
    }

    /**
     * Stored reflection for the week, generating it when the week has public
     * memories. Returns null for an empty week that was never generated.
     */
    public function recap(User $user, ?string $weekStart = null): ?JournalReflection
    {
        $week = $weekStart === null || $weekStart === ''
            ? $this->weekStart($user)
            : $this->parseWeekStart($user, $weekStart);

        $existing = $this->forWeek($user, $week);

        if ($existing !== null) {
            return $existing;
        }

        if ($this->publicPayload($user, $week)['text'] === '') {
            return null;
        }

        return $this->generate($user, $week);
    }

    public function generate(User $user, CarbonImmutable $weekStart): JournalReflection
    {
        $payload = $this->publicPayload($user, $weekStart);
        $body = null;
        $model = null;
        $source = 'fallback';

        if ($user->ai_enabled && $payload['text'] !== '') {
            $result = $this->generateWithAi($user, $payload['text']);

            if ($result !== null && trim($result['body']) !== '') {
                $body = $result['body'];
                $model = $result['model'] ?? null;
                $source = 'ai';
            }
        }

        if ($body === null) {
            $body = $this->fallback($user, $weekStart);
        }

        return JournalReflection::updateOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $weekStart->toDateString(),
            ],
            [
                'body' => $body,
                'source' => $source,
                'model' => $model,
                'generated_at' => now(),
            ],
        );
    }

    /**
     * AI generation lands in a later task; the fallback runs until then.
     *
     * @return array{body: string, model: string|null}|null
     */
    public function generateWithAi(User $user, string $payload): ?array
    {
        return null;
    }

    /**
     * Public-only digest of the week. Private days and moments never reach the
     * text, and long entries are trimmed before they leave the server.
     *
     * @return array{text: string, days: int, moods: array<int, int>}
     */
    public function publicPayload(User $user, CarbonImmutable $weekStart): array
    {
        $days = $this->publicDays($user, $weekStart);
        $moments = $this->publicMoments($user, $weekStart);

        $lines = [];

        foreach ($days as $day) {
            $lines[] = $this->dayHeader($day);
            $body = $this->trimText((string) $day->body, self::DAY_BODY_LIMIT);

            if ($body !== '') {
                $lines[] = $body;
            }
        }

        foreach ($moments as $moment) {
            $lines[] = $this->momentHeader($moment, $user);
            $body = $this->trimText((string) $moment->body, self::MOMENT_TEXT_LIMIT);

            if ($body !== '') {
                $lines[] = $body;
            }
        }

        return [
            'text' => mb_substr(implode("\n", $lines), 0, self::PAYLOAD_TEXT_LIMIT),
            'days' => $days->count(),
            'moods' => $days
                ->pluck('mood')
                ->filter(fn (?int $mood): bool => $mood !== null)
                ->countBy()
                ->map(fn (int $count): int => $count)
                ->all(),
        ];
    }

    public function fallback(User $user, CarbonImmutable $weekStart): string
    {
        $days = $this->publicDays($user, $weekStart);
        $moments = $this->publicMoments($user, $weekStart);
        $written = $days->count();
        $streak = $this->stats->streak($user);

        $lines = [
            'Semana del '.$weekStart->toDateString().' al '.$weekStart->addDays(6)->toDateString().'.',
            $written === 1 ? 'Escribiste 1 día.' : 'Escribiste '.$written.' días.',
            'Racha actual: '.$streak.($streak === 1 ? ' día.' : ' días.'),
        ];

        $moods = $days->pluck('mood')->filter(fn (?int $mood): bool => $mood !== null);

        if ($moods->isNotEmpty()) {
            $average = round((float) $moods->avg(), 1);
            $lines[] = 'Ánimo promedio: '.number_format($average, 1).'/5 ('.$this->moodLabel($average).').';
        }

        $tags = $this->topTags($days, $moments);

        if ($tags !== []) {
            $lines[] = 'Tags más usados: '.implode(', ', $tags).'.';
        }

        foreach ($days as $day) {
            $excerpt = $this->firstLine((string) $day->body, self::FALLBACK_EXCERPT_LIMIT);

            if ($excerpt !== '') {
                $lines[] = '';
                $lines[] = $this->dayHeader($day);
                $lines[] = $excerpt;
            }
        }

        if ($moments->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Momentos:';

            foreach ($moments->take(self::MAX_MOMENTS) as $moment) {
                $local = CarbonImmutable::instance($moment->occurred_at)
                    ->setTimezone($user->localTimezone());
                $title = $this->firstLine((string) $moment->title, self::MOMENT_TEXT_LIMIT);
                $excerpt = $this->firstLine((string) $moment->body, self::MOMENT_TEXT_LIMIT);
                $label = $title !== '' && $excerpt !== '' && $title !== $excerpt
                    ? $title.' — '.$excerpt
                    : ($title !== '' ? $title : $excerpt);

                $lines[] = '· '.$local->toDateString().($label !== '' ? ' · '.$label : '');
            }
        }

        $lines[] = '';
        $lines[] = 'Sin IA configurada; este resumen se armó con tus datos.';

        return mb_substr(implode("\n", $lines), 0, self::FALLBACK_TEXT_LIMIT);
    }

    /**
     * @return Collection<int, JournalDay>
     */
    private function publicDays(User $user, CarbonImmutable $weekStart): Collection
    {
        return JournalDay::query()
            ->forUser($user)
            ->where('is_private', false)
            ->whereBetween('date', [
                $weekStart->toDateString(),
                $weekStart->addDays(6)->toDateString(),
            ])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('body')->where('body', '!=', '');
                })
                    ->orWhereNotNull('mood');
            })
            ->orderBy('date')
            ->get();
    }

    /**
     * @return Collection<int, JournalMoment>
     */
    private function publicMoments(User $user, CarbonImmutable $weekStart): Collection
    {
        return JournalMoment::query()
            ->where('user_id', $user->id)
            ->where('is_private', false)
            ->where('occurred_at', '>=', $weekStart->utc())
            ->where('occurred_at', '<', $weekStart->addWeek()->utc())
            ->with('people:id,name')
            ->orderBy('occurred_at')
            ->get();
    }

    private function dayHeader(JournalDay $day): string
    {
        $header = 'Día '.$day->date->toDateString();

        if ($day->mood !== null) {
            $header .= ' · ánimo '.$day->mood.'/5';
        }

        $tags = $this->tags($day->tags);

        if ($tags !== []) {
            $header .= ' · tags: '.implode(', ', $tags);
        }

        return $header;
    }

    private function momentHeader(JournalMoment $moment, User $user): string
    {
        $local = CarbonImmutable::instance($moment->occurred_at)->setTimezone($user->localTimezone());
        $header = 'Momento '.$local->toDateString();
        $title = $this->trimText((string) $moment->title, self::MOMENT_TEXT_LIMIT);

        if ($title !== '') {
            $header .= ' · '.$title;
        }

        $tags = $this->tags($moment->tags);

        if ($tags !== []) {
            $header .= ' · tags: '.implode(', ', $tags);
        }

        $people = $moment->people->pluck('name')->implode(', ');

        if ($people !== '') {
            $header .= ' · con: '.$people;
        }

        return $header;
    }

    /**
     * @param  Collection<int, JournalDay>  $days
     * @param  Collection<int, JournalMoment>  $moments
     * @return array<int, string>
     */
    private function topTags(Collection $days, Collection $moments): array
    {
        $counts = $days
            ->flatMap(fn (JournalDay $day): array => $this->tags($day->tags))
            ->concat($moments->flatMap(fn (JournalMoment $moment): array => $this->tags($moment->tags)))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(self::MAX_TAGS)
            ->values()
            ->all();

        return array_map('strval', $counts);
    }

    private function moodLabel(float $average): string
    {
        return match (true) {
            $average < 1.5 => 'Bajo',
            $average < 2.5 => 'Flojo',
            $average < 3.5 => 'Normal',
            $average < 4.5 => 'Bien',
            default => 'Muy bien',
        };
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return array_values(array_filter($tags, fn (mixed $tag): bool => is_string($tag) && trim($tag) !== ''));
    }

    private function trimText(string $text, int $limit): string
    {
        return trim(mb_substr(trim($text), 0, $limit));
    }

    private function firstLine(string $text, int $limit): string
    {
        $line = trim((string) preg_split('/\R/', trim($text), 2)[0]);

        return mb_substr($line, 0, $limit);
    }

    private function parseWeekStart(User $user, string $value): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value, $user->localTimezone());
        } catch (Throwable) {
            throw new InvalidArgumentException('La semana debe tener el formato Y-m-d.');
        }

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('La semana debe tener el formato Y-m-d.');
        }

        if ($parsed->dayOfWeek !== CarbonInterface::MONDAY) {
            throw new InvalidArgumentException('La semana debe empezar un lunes.');
        }

        $current = $this->weekStart($user);
        $earliest = $current->subWeeks(self::RECAP_WEEKS - 1);

        if ($parsed->lt($earliest) || $parsed->gt($current)) {
            throw new InvalidArgumentException('La semana está fuera de las últimas 8 semanas.');
        }

        return $parsed;
    }
}
