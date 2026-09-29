<?php

namespace App\Services\Projects;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class ProjectService
{
    public function __construct(protected ProjectTypeService $types) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Project
    {
        $type = $data['type'] ?? null;

        if (! in_array($type, ['personal', 'freelance'], true)) {
            throw new InvalidArgumentException('A valid project type (personal or freelance) is required.');
        }

        if ($type === 'freelance') {
            $clientId = $data['client_id'] ?? null;

            if (! $clientId) {
                throw new InvalidArgumentException('Freelance projects require a client.');
            }

            $this->assertClientOwnership($user, (int) $clientId);
        } else {
            $data['client_id'] = $data['client_id'] ?? null;

            if ($data['client_id']) {
                $this->assertClientOwnership($user, (int) $data['client_id']);
            }
        }

        $data['user_id'] = $user->id;
        $data['type'] = $type;
        $data['total_amount'] = $data['total_amount'] ?? 0;

        if (empty($data['currency_id'])) {
            $data['currency_id'] = Currency::query()->value('id');
        }

        return Project::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Project $project, array $data): Project
    {
        $this->assertOwnership($user, $project);

        if (array_key_exists('client_id', $data) && $data['client_id']) {
            $this->assertClientOwnership($user, (int) $data['client_id']);
        }

        if (array_key_exists('type', $data) && $data['type'] !== $project->type) {
            $type = $data['type'];
            $clientId = $data['client_id'] ?? $project->client_id;

            unset($data['type'], $data['client_id']);

            $project = $this->types->changeType($user, $project, (string) $type, $clientId ? (int) $clientId : null);
        }

        if ($data !== []) {
            $project->update($data);
        }

        return $project->fresh();
    }

    public function archive(User $user, Project $project): Project
    {
        $this->assertOwnership($user, $project);

        $project->update(['is_archived' => true]);

        return $project->fresh();
    }

    public function delete(User $user, Project $project): void
    {
        $this->assertOwnership($user, $project);

        $project->delete();
    }

    private function assertOwnership(User $user, Project $project): void
    {
        if ($project->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this project.');
        }
    }

    private function assertClientOwnership(User $user, int $clientId): void
    {
        if (! Client::where('user_id', $user->id)->whereKey($clientId)->exists()) {
            throw new ModelNotFoundException('Client not found.');
        }
    }
}
