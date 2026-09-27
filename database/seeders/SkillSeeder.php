<?php

namespace Database\Seeders;

use App\Ai\Skills\SkillCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    /**
     * Import the bundled skills (brainstorming, using-superpowers,
     * writing-plans, anti-ui-slop, ui-radar) for every AI-enabled user.
     */
    public function run(): void
    {
        $catalog = app(SkillCatalog::class);
        $path = database_path('seeders/skills');
        $files = glob($path.'/*.md') ?: [];

        foreach (User::query()->where('ai_enabled', true)->get() as $user) {
            foreach ($files as $file) {
                $catalog->importFromMarkdown(
                    $user,
                    (string) file_get_contents($file),
                    basename($file),
                );
            }
        }
    }
}
