<?php

namespace App\Ai\Memory;

use App\Ai\Enums\MemoryScope;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MemoryCatalog
{
    public const MAX_CONTENT = 500;

    public const MAX_GLOBAL = 100;

    public const MAX_THREAD = 50;

    public const INJECTION_BUDGET = 8000;

    public static function hashContent(string $content): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($content)));

        return hash('sha256', $normalized ?? $content);
    }

    public function remember(User $user, string $content, MemoryScope $scope, ?ChatThread $thread = null, string $source = 'agent'): Memory
    {
        $content = $this->validateContent($content);

        if ($scope === MemoryScope::Thread && $thread === null) {
            throw ValidationException::withMessages(['scope' => 'La memoria del hilo requiere un hilo.']);
        }

        $existing = $this->queryFor($user, $scope, $thread)
            ->where('content_hash', self::hashContent($content))
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['content' => $content, 'source' => $source])->save();

            return $existing;
        }

        $max = $scope === MemoryScope::Global ? self::MAX_GLOBAL : self::MAX_THREAD;

        if ($this->queryFor($user, $scope, $thread)->count() >= $max) {
            throw ValidationException::withMessages([
                'content' => 'Límite de '.$max.' memorias alcanzado. Olvidá algunas antes de guardar más.',
            ]);
        }

        return Memory::create([
            'user_id' => $user->getKey(),
            'scope' => $scope,
            'thread_id' => $scope === MemoryScope::Thread ? $thread?->getKey() : null,
            'content' => $content,
            'source' => $source,
            'content_hash' => self::hashContent($content),
            'metadata' => null,
        ]);
    }

    public function update(Memory $memory, string $content): Memory
    {
        $content = $this->validateContent($content);
        $hash = self::hashContent($content);

        $duplicate = Memory::query()
            ->where('user_id', $memory->user_id)
            ->where('scope', $memory->scope->value)
            ->whereKeyNot($memory->getKey())
            ->where('content_hash', $hash)
            ->where(function (Builder $query) use ($memory): void {
                $memory->thread_id === null
                    ? $query->whereNull('thread_id')
                    : $query->where('thread_id', $memory->thread_id);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['content' => 'Ya existe una memoria igual en este ámbito.']);
        }

        $memory->forceFill(['content' => $content, 'content_hash' => $hash])->save();

        return $memory;
    }

    public function forget(Memory $memory): void
    {
        $memory->delete();
    }

    public function promote(Memory $memory): Memory
    {
        if ($memory->scope !== MemoryScope::Thread) {
            return $memory;
        }

        $globalCount = Memory::query()
            ->where('user_id', $memory->user_id)
            ->where('scope', MemoryScope::Global->value)
            ->count();

        if ($globalCount >= self::MAX_GLOBAL) {
            throw ValidationException::withMessages([
                'content' => 'Límite de '.self::MAX_GLOBAL.' memorias generales alcanzado.',
            ]);
        }

        $memory->forceFill([
            'scope' => MemoryScope::Global,
            'thread_id' => null,
            'metadata' => array_merge((array) $memory->metadata, ['promoted_at' => now()->toIso8601String()]),
        ])->save();

        return $memory;
    }

    /**
     * @return Collection<int, Memory>
     */
    public function candidatesFor(User $user, ?ChatThread $thread, string $query): Collection
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return collect();
        }

        return Memory::query()
            ->forUser($user)
            ->where(function (Builder $builder) use ($thread): void {
                $builder->where('scope', MemoryScope::Global->value);

                if ($thread !== null) {
                    $builder->orWhere(fn (Builder $query) => $query
                        ->where('scope', MemoryScope::Thread->value)
                        ->where('thread_id', $thread->getKey()));
                }
            })
            ->whereRaw("LOWER(content) LIKE ? ESCAPE '\\'", ['%'.addcslashes($needle, '%_\\').'%'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function blockFor(User $user, ?ChatThread $thread): ?string
    {
        $budget = self::INJECTION_BUDGET;

        $sections = array_values(array_filter([
            $this->renderSection('Memoria general del usuario', $this->globalMemories($user), $budget),
            $thread === null ? null : $this->renderSection('Memoria de este hilo', $this->threadMemories($user, $thread), $budget),
        ]));

        return $sections === [] ? null : implode("\n\n", $sections);
    }

    /**
     * @return Collection<int, Memory>
     */
    public function globalMemories(User $user): Collection
    {
        return Memory::query()->forUser($user)->global()->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /**
     * @return Collection<int, Memory>
     */
    public function threadMemories(User $user, ChatThread $thread): Collection
    {
        return Memory::query()->forUser($user)->forThread($thread)->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, Memory>  $memories
     */
    protected function renderSection(string $header, Collection $memories, int &$budget): ?string
    {
        if ($memories->isEmpty()) {
            return null;
        }

        $lines = [];
        $skipped = 0;

        foreach ($memories as $memory) {
            $line = '- ['.$memory->getKey().'] '.preg_replace('/\s+/u', ' ', $memory->content);
            $cost = mb_strlen($line) + 1;

            if ($cost > $budget) {
                $skipped++;

                continue;
            }

            $budget -= $cost;
            $lines[] = $line;
        }

        if ($lines === []) {
            return $header.":\n(+".$skipped.' memorias no mostradas)';
        }

        $text = $header.":\n".implode("\n", $lines);

        if ($skipped > 0) {
            $text .= "\n(+".$skipped.' memorias no mostradas)';
        }

        return $text;
    }

    protected function validateContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages(['content' => 'La memoria no puede estar vacía.']);
        }

        if (mb_strlen($content) > self::MAX_CONTENT) {
            throw ValidationException::withMessages([
                'content' => 'Máximo '.self::MAX_CONTENT.' caracteres por memoria.',
            ]);
        }

        return $content;
    }

    protected function queryFor(User $user, MemoryScope $scope, ?ChatThread $thread): Builder
    {
        $query = Memory::query()->forUser($user)->where('scope', $scope->value);

        return $scope === MemoryScope::Thread
            ? $query->where('thread_id', $thread?->getKey())
            : $query->whereNull('thread_id');
    }
}
