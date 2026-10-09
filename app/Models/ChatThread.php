<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Throwable;

class ChatThread extends Conversation
{
    use HasFactory;

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_HEALTH = 'salud';

    /**
     * Module key of the health assistant. Health threads were born before the
     * `module` column and are still identified by `category = 'salud'`, so this
     * constant is only the key they resolve to as a module.
     */
    public const MODULE_HEALTH = 'health';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
            'tools_policy' => 'array',
            'tools_policy_backup' => 'array',
            'deep_context' => 'boolean',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'thread_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Library documents explicitly attached to this thread (N:N).
     *
     * @return BelongsToMany<ChatAttachment, $this>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(ChatAttachment::class, 'chat_thread_sources', 'thread_id', 'attachment_id')
            ->withTimestamps();
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey());
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    public function scopeWithMessages(Builder $query): void
    {
        $query->whereHas('messages');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('pinned_at')->orderByDesc('updated_at');
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where('title', 'like', '%'.addcslashes($term, '%_').'%');
    }

    public function belongsToUser(User $user): bool
    {
        return $this->participant_type === $user->getMorphClass()
            && (int) $this->participant_id === (int) $user->getKey();
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * Build an untrusted, formatted context block from the thread's indexed
     * documents that match the given query. Returns null when there is
     * nothing relevant (or the query has no usable terms).
     *
     * With $expand ("comprensión extendida") the FTS runs with a 30-hit
     * budget and every hit is widened with its ±3 neighbour chunks, deduped
     * and capped to 60.000 characters (~15k tokens).
     */
    public function documentContext(string $query, int $limit = 10, bool $expand = false): ?string
    {
        $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->values();

        if ($terms->isEmpty() || $this->participant_id === null) {
            return null;
        }

        $searchLimit = $expand ? 30 : $limit;

        try {
            $rows = DB::connection()->getDriverName() === 'pgsql'
                ? $this->searchDocumentsOnPostgres($terms, $searchLimit)
                : $this->searchDocumentsOnSqlite($terms, $searchLimit);
        } catch (Throwable) {
            return null;
        }

        if ($rows === []) {
            // Fallback: aunque la pregunta no matchee términos con el FTS,
            // el asesor debe saber qué documentos adjuntos existen. Se usa la
            // búsqueda por orden de relevancia general (últimos documentos).
            $rows = $this->fallbackDocuments($searchLimit);

            if ($rows === []) {
                return null;
            }

            if ($expand) {
                return "Documentos del hilo disponibles (sin coincidencia textual con la consulta):\n"
                    .$this->formatDocumentRows($this->capDocumentRows($rows));
            }

            return "Documentos del hilo disponibles (sin coincidencia textual con la consulta):\n"
                .collect($rows)
                    ->map(fn (object $row): string => '### '.$row->original_name."\n".$row->content)
                    ->implode("\n\n");
        }

        if ($expand) {
            $rows = $this->capDocumentRows($this->expandDocumentRows($rows));
        }

        return collect($rows)
            ->map(fn (object $row): string => '### '.$row->original_name."\n".$row->content)
            ->implode("\n\n");
    }

    /**
     * Widen every FTS hit with its ±3 neighbour chunks of the same
     * attachment. Dedupes by chunk id and orders by attachment + position.
     *
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    private function expandDocumentRows(array $rows): array
    {
        $positionsByAttachment = [];
        $namesByAttachment = [];
        $byId = [];

        foreach ($rows as $row) {
            if (! isset($row->id, $row->attachment_id, $row->position)) {
                continue;
            }

            $attachmentId = (string) $row->attachment_id;
            $positionsByAttachment[$attachmentId][] = (int) $row->position;
            $namesByAttachment[$attachmentId] ??= $row->original_name ?? '';
            $byId[(int) $row->id] = $row;
        }

        if ($positionsByAttachment === []) {
            return $rows;
        }

        $neighbours = ChatDocumentChunk::query()
            ->whereIn('attachment_id', array_keys($positionsByAttachment))
            ->get(['id', 'attachment_id', 'position', 'content']);

        foreach ($neighbours as $chunk) {
            $attachmentId = (string) $chunk->attachment_id;

            $nearHit = false;

            foreach ($positionsByAttachment[$attachmentId] ?? [] as $position) {
                if (abs((int) $chunk->position - $position) <= 3) {
                    $nearHit = true;

                    break;
                }
            }

            if (! $nearHit) {
                continue;
            }

            if (! isset($byId[(int) $chunk->getKey()])) {
                $row = (object) [
                    'id' => $chunk->getKey(),
                    'attachment_id' => $chunk->attachment_id,
                    'position' => $chunk->position,
                    'content' => $chunk->content,
                    'original_name' => $namesByAttachment[$attachmentId] ?? '',
                ];
                $byId[(int) $chunk->getKey()] = $row;
            }
        }

        $expanded = array_values($byId);

        usort($expanded, fn (object $a, object $b): int => ((string) $a->attachment_id) <=> ((string) $b->attachment_id)
            ?: ((int) $a->position <=> (int) $b->position));

        return $expanded;
    }

    /**
     * Cap the formatted rows to 60.000 characters (~15k tokens), keeping
     * whole chunks in attachment + position order.
     *
     * @param  array<int, object>  $rows
     * @return array<int, object>
     */
    private function capDocumentRows(array $rows): array
    {
        $kept = [];
        $total = 0;

        foreach ($rows as $row) {
            $block = '### '.($row->original_name ?? '')."\n".($row->content ?? '');
            $length = mb_strlen($block) + ($kept === [] ? 0 : 2);

            if ($total + $length > 60000) {
                break;
            }

            $kept[] = $row;
            $total += $length;
        }

        if ($kept === [] && $rows !== []) {
            // Un solo bloque ya supera el techo: se trunca su contenido para
            // no romper el contrato de 60.000 caracteres.
            $first = clone $rows[0];
            $prefix = '### '.($first->original_name ?? '')."\n";
            $first->content = mb_substr((string) ($first->content ?? ''), 0, max(0, 60000 - mb_strlen($prefix)));

            return [$first];
        }

        return $kept;
    }

    /**
     * Format rows with the same '### name + content' shape used everywhere.
     *
     * @param  array<int, object>  $rows
     */
    private function formatDocumentRows(array $rows): string
    {
        return collect($rows)
            ->map(fn (object $row): string => '### '.$row->original_name."\n".$row->content)
            ->implode("\n\n");
    }

    /**
     * @param  Collection<int, string>  $terms
     * @return array<int, object>
     */
    private function searchDocumentsOnSqlite(Collection $terms, int $limit): array
    {
        $match = $terms
            ->map(fn (string $term): string => $term.'*')
            ->implode(' OR ');

        return DB::select(
            'select c.id, c.attachment_id, c.position, c.content, a.original_name
             from chat_document_chunks_fts
             join chat_document_chunks c on c.id = chat_document_chunks_fts.rowid
             join chat_attachments a on a.id = c.attachment_id
             join chat_thread_sources s on s.attachment_id = a.id
             where chat_document_chunks_fts match ?
               and s.thread_id = ?
               and a.user_id = ?
               and a.status = ?
             order by bm25(chat_document_chunks_fts)
             limit ?',
            [$match, $this->id, $this->participant_id, 'indexed', $limit],
        );
    }

    /**
     * Últimos documentos indexados del hilo para inyectar contexto cuando el
     * FTS no matchea términos de la consulta.
     *
     * @return array<int, object>
     */
    private function fallbackDocuments(int $limit): array
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? DB::select(
                'select c.id, c.attachment_id, c.position, c.content, a.original_name
                 from chat_document_chunks c
                 join chat_attachments a on a.id = c.attachment_id
                 join chat_thread_sources s on s.attachment_id = a.id
                 where s.thread_id = ?
                   and a.user_id = ?
                   and a.status = ?
                 order by a.created_at desc, c.position asc
                 limit ?',
                [$this->id, $this->participant_id, 'indexed', $limit],
            )
            : DB::select(
                'select c.id, c.attachment_id, c.position, c.content, a.original_name
                 from chat_document_chunks c
                 join chat_attachments a on a.id = c.attachment_id
                 join chat_thread_sources s on s.attachment_id = a.id
                 where s.thread_id = ?
                   and a.user_id = ?
                   and a.status = ?
                 order by a.created_at desc, c.position asc
                 limit ?',
                [$this->id, $this->participant_id, 'indexed', $limit],
            );
    }

    /**
     * @param  Collection<int, string>  $terms
     * @return array<int, object>
     */
    private function searchDocumentsOnPostgres(Collection $terms, int $limit): array
    {
        $match = $terms
            ->map(fn (string $term): string => $term.':*')
            ->implode(' | ');

        return DB::select(
            "select c.id, c.attachment_id, c.position, c.content, a.original_name
             from chat_document_chunks c
             join chat_attachments a on a.id = c.attachment_id
             join chat_thread_sources s on s.attachment_id = a.id
             where to_tsvector('simple', c.content) @@ to_tsquery('simple', ?)
               and s.thread_id = ?
               and a.user_id = ?
               and a.status = ?
             order by ts_rank(to_tsvector('simple', c.content), to_tsquery('simple', ?)) desc
             limit ?",
            [$match, $this->id, $this->participant_id, 'indexed', $match, $limit],
        );
    }
}
