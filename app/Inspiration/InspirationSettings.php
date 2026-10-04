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
     */
    public function for(User $user): SettingsBag
    {
        $row = InspirationSetting::query()->whereKey($user->getKey())->first();

        return SettingsBag::fromArray($row?->body ?? []);
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
