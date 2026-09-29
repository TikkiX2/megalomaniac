<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PersonInteraction;

class PersonInteractionObserver
{
    public function saved(PersonInteraction $interaction): void
    {
        $interaction->person?->refreshLastContactedAt();
    }

    public function deleted(PersonInteraction $interaction): void
    {
        $interaction->person?->refreshLastContactedAt();
    }
}
