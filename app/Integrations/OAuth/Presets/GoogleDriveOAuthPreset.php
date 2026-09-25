<?php

namespace App\Integrations\OAuth\Presets;

class GoogleDriveOAuthPreset extends GoogleOAuthPreset
{
    /**
     * @return string[]
     */
    public function scopes(): array
    {
        return [
            'https://www.googleapis.com/auth/drive',
        ];
    }
}
