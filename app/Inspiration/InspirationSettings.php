<?php

declare(strict_types=1);

namespace App\Inspiration;

use App\Inspiration\Dtos\SettingsBag;
use App\Models\InspirationSetting;
use App\Models\User;

class InspirationSettings
{
    /**
     * Resolve the user's settings, always returning a well-formed bag even
     * when no row exists yet.
     *
     * A missing row means a fresh account: the bag carries the curated
     * `default_enabled_sources` so the explore wall is populated without any
     * setup. Once a row exists it is authoritative — including an explicitly
     * empty `enabled_sources`, which means the user turned everything off.
     */
    public function for(User $user): SettingsBag
    {
        $row = InspirationSetting::query()->whereKey($user->getKey())->first();

        if ($row === null) {
            return SettingsBag::fromArray([
                'enabled_sources' => config('inspiration.default_enabled_sources', []),
            ]);
        }

        return SettingsBag::fromArray($row->body ?? []);
    }

    /**
     * Persist the complete settings shape, filling missing keys with defaults.
     *
     * @param  array<string, mixed>  $body
     */
    public function update(User $user, array $body): void
    {
        InspirationSetting::updateOrCreate(
            ['user_id' => $user->getKey()],
            ['body' => SettingsBag::fromArray($body)->toArray()],
        );
    }
}
