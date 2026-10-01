<?php

declare(strict_types=1);

namespace App\Ai\Support;

use Laravel\Ai\Responses\AgentResponse;

/**
 * Result of an eager `surface:insights` request, keeping the two failure
 * flavours apart.
 *
 * "Not configured" and "every provider failed" both mean the caller has
 * nothing to show, but they are not the same news for the user: the first one
 * is fixed in Settings, the second one is a provider outage worth reporting.
 * The outcome carries both cases so the endpoints can answer 200 with copy
 * that tells the truth about which one happened.
 */
final readonly class AiInsightOutcome
{
    /**
     * Honest copy for a chain that was tried and exhausted.
     *
     * Matches the total-error wording of the design spec (step 8 in the
     * frontend contract): the user is told the providers failed and pointed at
     * Settings → IA, never that AI is "not configured".
     */
    public const EXHAUSTED_MESSAGE = 'Todos los proveedores de IA fallaron. Revisá Settings → IA.';

    private function __construct(
        public ?AgentResponse $response,
        public bool $exhausted,
    ) {}

    /** No usable provider resolved: the caller has nothing configured to run. */
    public static function unconfigured(): self
    {
        return new self(null, false);
    }

    /** Every provider in the chain was tried and failed. */
    public static function exhausted(): self
    {
        return new self(null, true);
    }

    public static function resolved(AgentResponse $response): self
    {
        return new self($response, false);
    }

    /** The response text, or null when nothing came back. */
    public function text(): ?string
    {
        return $this->response?->text;
    }

    /**
     * The text for the caller methods that surface the insight itself.
     *
     * An exhausted chain becomes the honest copy — this is the same "no data"
     * result those methods already return for every other dead end — while a
     * genuinely unconfigured user keeps the null that its controller turns
     * into its historical message.
     */
    public function honestText(): ?string
    {
        return $this->text() ?? ($this->exhausted ? self::EXHAUSTED_MESSAGE : null);
    }

    /** True when there is no response to work with. */
    public function isEmpty(): bool
    {
        return $this->response === null;
    }

    /**
     * Copy for the user when there is nothing to show.
     *
     * @param  string  $notConfigured  Existing wording for the genuinely
     *                                 unconfigured case, kept byte-identical.
     */
    public function message(string $notConfigured): string
    {
        return $this->exhausted ? self::EXHAUSTED_MESSAGE : $notConfigured;
    }
}
