<?php

namespace App\Http\Controllers\Settings;

use App\Ai\Skills\SkillCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Skills\ImportSkillRequest;
use App\Http\Requests\Skills\StoreSkillRequest;
use App\Http\Requests\Skills\UpdateSkillRequest;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillController extends Controller
{
    public function __construct(protected SkillCatalog $catalog) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/skills', [
            'skills' => Skill::query()
                ->forUser($request->user())
                ->orderBy('name')
                ->get()
                ->map(fn (Skill $skill): array => [
                    'id' => $skill->id,
                    'key' => $skill->key,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'instructions' => $skill->instructions,
                    'enabled' => $skill->enabled,
                    'source' => $skill->source,
                ])
                ->values()
                ->all(),
            'limits' => ['max' => SkillCatalog::MAX_SKILLS],
        ]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        $this->catalog->create($request->user(), $request->validated());

        return to_route('skills.index');
    }

    public function update(UpdateSkillRequest $request, Skill $skill): RedirectResponse
    {
        $this->authorizeSkill($request, $skill);

        $this->catalog->update($skill, $request->validated());

        return to_route('skills.index');
    }

    public function destroy(Request $request, Skill $skill): RedirectResponse
    {
        $this->authorizeSkill($request, $skill);

        $this->catalog->delete($skill);

        return to_route('skills.index');
    }

    public function toggle(Request $request, Skill $skill): RedirectResponse
    {
        $this->authorizeSkill($request, $skill);

        $this->catalog->toggle($skill, $request->boolean('enabled'));

        return to_route('skills.index');
    }

    public function import(ImportSkillRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        $this->catalog->importFromMarkdown(
            $request->user(),
            (string) file_get_contents((string) $file->getRealPath()),
            $file->getClientOriginalName(),
        );

        return to_route('skills.index');
    }

    protected function authorizeSkill(Request $request, Skill $skill): void
    {
        abort_unless($skill->user_id === $request->user()?->getKey(), 404);
    }
}
