<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Exceptions\DownloadQuotaExceededException;
use App\Inspiration\Exceptions\DuplicateSavedImageException;
use App\Jobs\Inspiration\DownloadFullJob;
use App\Jobs\Inspiration\DownloadThumbJob;
use App\Models\Moodboard;
use App\Models\Project;
use App\Models\SavedImage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Owns the moodboard bootstrap and the save/download lifecycle.
 *
 * Dedupe runs a pre-check and, because two requests can race past it, the
 * create is wrapped so a database unique violation is surfaced as the same
 * DuplicateSavedImageException (the controller answers both with a 409).
 * Thumbnail and full downloads are always queued after commit.
 */
class InspirationSaveService
{
    /**
     * Item fields persisted verbatim from the validated payload.
     *
     * `thumbnail_url` is intentionally absent: it is only forwarded to the
     * thumbnail job (the table has no remote thumbnail column).
     */
    private const PERSISTED_FIELDS = [
        'source',
        'source_id',
        'title',
        'author',
        'author_url',
        'page_url',
        'image_url',
        'width',
        'height',
        'tags',
        'license',
        'maturity',
        'note',
    ];

    public function __construct(private readonly SourceManager $sources) {}

    /**
     * Boards the user can save into: the Inbox plus one per personal project.
     *
     * Each moodboard carries its `project` relation and a `saved_images_count`.
     *
     * @return Collection<int, Moodboard>
     */
    public function boards(User $user): Collection
    {
        return Moodboard::query()
            ->forUser($user)
            ->with('project:id,name')
            ->withCount('savedImages')
            ->orderBy('id')
            ->get();
    }

    /**
     * Return the user's Inbox, creating it on first use (idempotent).
     */
    public function ensureInbox(User $user): Moodboard
    {
        $inbox = Moodboard::query()->forUser($user)->whereNull('project_id')->first();

        if ($inbox !== null) {
            return $inbox;
        }

        try {
            return Moodboard::create([
                'user_id' => $user->id,
                'project_id' => null,
                'name' => 'Inbox',
            ]);
        } catch (QueryException) {
            // Partial unique index: a concurrent call won the race.
            return Moodboard::query()->forUser($user)->whereNull('project_id')->firstOrFail();
        }
    }

    /**
     * Return the moodboard bound to a personal project, creating it lazily.
     *
     * Only the project owner may target a `personal` project.
     */
    public function ensureMoodboardForProject(User $user, Project $project): Moodboard
    {
        if ($project->user_id !== $user->id || ! $project->isPersonal()) {
            throw new AuthorizationException('Los moodboards solo admiten proyectos personales propios.');
        }

        $board = Moodboard::query()->forUser($user)->where('project_id', $project->id)->first();

        if ($board !== null) {
            return $board;
        }

        try {
            return Moodboard::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'name' => $project->name,
            ]);
        } catch (QueryException) {
            return Moodboard::query()->forUser($user)->where('project_id', $project->id)->firstOrFail();
        }
    }

    /**
     * Persist an item into a board and queue its thumbnail download.
     *
     * @param  array<string, mixed>  $item
     *
     * @throws DuplicateSavedImageException
     */
    public function save(User $user, array $item, Moodboard $board): SavedImage
    {
        if ($board->user_id !== $user->id) {
            throw new AuthorizationException('No puedes guardar en el moodboard de otro usuario.');
        }

        $source = (string) ($item['source'] ?? '');

        if ($this->sources->get($source) === null) {
            throw new InvalidArgumentException("Unknown inspiration source [{$source}].");
        }

        $sourceId = (string) ($item['source_id'] ?? '');
        $existing = $this->duplicate($user, $source, $sourceId);

        if ($existing !== null) {
            throw new DuplicateSavedImageException($existing);
        }

        try {
            $saved = SavedImage::create([
                ...$this->attributes($item),
                'user_id' => $user->id,
                'moodboard_id' => $board->id,
                'download_status' => SavedImage::STATUS_THUMB,
            ]);
        } catch (QueryException $exception) {
            // Belt & suspenders: a concurrent save may slip past the pre-check.
            $existing = $this->duplicate($user, $source, $sourceId);

            if ($existing !== null) {
                throw new DuplicateSavedImageException($existing);
            }

            throw $exception;
        }

        DownloadThumbJob::dispatch($saved, $item['thumbnail_url'] ?? null)
            ->onQueue('default')
            ->afterCommit();

        return $saved;
    }

    /**
     * Queue an on-demand full-size download, enforcing the daily quota.
     *
     * @throws DownloadQuotaExceededException
     */
    public function requestFullDownload(User $user, SavedImage $saved): void
    {
        if ($saved->user_id !== $user->id) {
            throw new AuthorizationException('No puedes descargar una imagen de otro usuario.');
        }

        if ($saved->download_status === SavedImage::STATUS_FULL) {
            return;
        }

        $downloadedToday = SavedImage::query()
            ->forUser($user)
            ->where('downloaded_at', '>=', Carbon::today())
            ->count();

        if ($downloadedToday >= (int) config('inspiration.max_downloads_per_day')) {
            throw new DownloadQuotaExceededException;
        }

        DownloadFullJob::dispatch($saved)
            ->onQueue('default')
            ->afterCommit();
    }

    private function duplicate(User $user, string $source, string $sourceId): ?SavedImage
    {
        return SavedImage::query()
            ->forUser($user)
            ->where('source', $source)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributes(array $item): array
    {
        $attributes = [];

        foreach (self::PERSISTED_FIELDS as $field) {
            if (array_key_exists($field, $item)) {
                $attributes[$field] = $item[$field];
            }
        }

        return $attributes;
    }
}
