<?php

namespace App\Ai\Skills;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * User-scoped catalog of reusable instructions ("skills") the chat agent can
 * discover by description and load on demand, or the user can invoke for a
 * single turn.
 */
class SkillCatalog
{
    public const MAX_SKILLS = 30;

    /**
     * @return array<int, array{key: string, name: string, description: ?string}>
     */
    public function summariesFor(User $user): array
    {
        return Skill::query()
            ->forUser($user)
            ->enabled()
            ->orderBy('name')
            ->get(['key', 'name', 'description'])
            ->map(fn (Skill $skill): array => [
                'key' => $skill->key,
                'name' => $skill->name,
                'description' => $skill->description,
            ])
            ->values()
            ->all();
    }

    public function instructionsFor(User $user, string $key): ?string
    {
        return Skill::query()
            ->forUser($user)
            ->enabled()
            ->where('key', $key)
            ->value('instructions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Skill
    {
        if (Skill::query()->forUser($user)->count() >= self::MAX_SKILLS) {
            throw ValidationException::withMessages([
                'name' => 'Límite de '.self::MAX_SKILLS.' skills alcanzado.',
            ]);
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'El nombre es obligatorio.']);
        }

        $base = Str::slug((string) ($data['key'] ?? $name));

        return Skill::create([
            'user_id' => $user->getKey(),
            'key' => $this->uniqueKey($user, $base !== '' ? $base : 'skill'),
            'name' => $name,
            'description' => filled($data['description'] ?? null) ? (string) $data['description'] : null,
            'instructions' => (string) ($data['instructions'] ?? ''),
            'enabled' => (bool) ($data['enabled'] ?? true),
            'source' => (string) ($data['source'] ?? 'manual'),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Skill $skill, array $data): Skill
    {
        $fields = array_intersect_key($data, array_flip(['name', 'description', 'instructions', 'enabled']));

        if (isset($fields['description']) && blank($fields['description'])) {
            $fields['description'] = null;
        }

        $skill->fill($fields)->save();

        return $skill;
    }

    public function delete(Skill $skill): void
    {
        $skill->delete();
    }

    public function toggle(Skill $skill, bool $enabled): Skill
    {
        $skill->forceFill(['enabled' => $enabled])->save();

        return $skill;
    }

    /**
     * Import a SKILL.md-style document. Frontmatter (--- name: ... ---) wins;
     * otherwise the first H1 becomes the name and the next paragraph the
     * description.
     */
    public function importFromMarkdown(User $user, string $markdown, string $filename): Skill
    {
        [$meta, $body] = $this->splitFrontMatter($markdown);

        $name = trim((string) ($meta['name'] ?? '')) ?: $this->guessName($body) ?: pathinfo($filename, PATHINFO_FILENAME);
        $description = trim((string) ($meta['description'] ?? '')) ?: $this->guessDescription($body);
        $instructions = trim($body) !== '' ? trim($body) : trim($markdown);

        // Re-importing the same file updates it instead of duplicating.
        $existing = Skill::query()->forUser($user)->where('name', $name)->first();

        if ($existing !== null) {
            $existing->forceFill([
                'description' => $description,
                'instructions' => $instructions,
                'source' => 'import',
                'metadata' => ['filename' => $filename],
            ])->save();

            return $existing;
        }

        return $this->create($user, [
            'name' => $name,
            'description' => $description,
            'instructions' => $instructions,
            'source' => 'import',
            'metadata' => ['filename' => $filename],
        ]);
    }

    protected function uniqueKey(User $user, string $base): string
    {
        $key = $base;
        $suffix = 2;

        while (Skill::query()->forUser($user)->where('key', $key)->exists()) {
            $key = $base.'-'.$suffix++;
        }

        return $key;
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function splitFrontMatter(string $markdown): array
    {
        $markdown = str_replace("\r\n", "\n", $markdown);

        if (! str_starts_with($markdown, "---\n")) {
            return [[], $markdown];
        }

        $end = strpos($markdown, "\n---", 4);

        if ($end === false) {
            return [[], $markdown];
        }

        return [
            $this->parseFrontMatter(substr($markdown, 4, $end - 4)),
            ltrim(substr($markdown, $end + 4), "\n"),
        ];
    }

    /**
     * Minimal `key: value` frontmatter parser. symfony/yaml is only a
     * transitive dev dependency, so the production build cannot rely on it.
     *
     * @return array<string, string>
     */
    protected function parseFrontMatter(string $raw): array
    {
        $meta = [];

        foreach (preg_split('/\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $position = strpos($line, ':');

            if ($position === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $position)));
            $value = trim(substr($line, $position + 1));

            if ($key === '' || $value === '') {
                continue;
            }

            $quote = $value[0];

            if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && str_ends_with($value, $quote)) {
                $value = substr($value, 1, -1);

                if ($quote === '"') {
                    $value = stripcslashes($value);
                }
            }

            $meta[$key] = $value;
        }

        return $meta;
    }

    protected function guessName(string $body): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function guessDescription(string $body): ?string
    {
        $lines = preg_split('/\n/', $body) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || $line === '---') {
                continue;
            }

            return Str::limit(trim($line, " \t*>_"), 200);
        }

        return null;
    }
}
