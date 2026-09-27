<?php

namespace App\Console\Commands;

use App\Ai\Skills\SkillCatalog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ImportSkillsCommand extends Command
{
    protected $signature = 'skills:import
        {--user= : Import into this user id only}
        {--all : Import into every user (default: AI-enabled users only)}
        {--path= : Directory with the SKILL.md files (default: database/seeders/skills)}';

    protected $description = 'Import the bundled SKILL.md files into the skills catalog of the target users';

    public function handle(SkillCatalog $catalog): int
    {
        $path = (string) ($this->option('path') ?: database_path('seeders/skills'));

        if (! is_dir($path)) {
            $this->error("Skills directory not found: {$path}");

            return self::FAILURE;
        }

        $files = glob($path.'/*.md') ?: [];

        if ($files === []) {
            $this->warn("No .md files found in {$path}");

            return self::SUCCESS;
        }

        $users = $this->targetUsers();

        if ($users->isEmpty()) {
            $this->error('No target users found.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            foreach ($files as $file) {
                $skill = $catalog->importFromMarkdown(
                    $user,
                    (string) file_get_contents($file),
                    basename($file),
                );

                $this->line(sprintf('user#%d → %s (%s)', $user->getKey(), $skill->name, $skill->key));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    protected function targetUsers(): Collection
    {
        if ($userId = $this->option('user')) {
            return User::query()->whereKey($userId)->get();
        }

        if ($this->option('all')) {
            return User::query()->get();
        }

        return User::query()->where('ai_enabled', true)->get();
    }
}
