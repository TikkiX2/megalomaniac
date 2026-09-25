<?php

namespace App\Ai\Providers;

use App\Ai\Gateway\ReasoningOpenAiCompatibleGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;

class ReasoningOpenAiCompatibleProvider extends OpenAiCompatibleProvider
{
    #[\Override]
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new ReasoningOpenAiCompatibleGateway($this->events);
    }
}
