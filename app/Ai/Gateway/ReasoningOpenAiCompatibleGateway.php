<?php

namespace App\Ai\Gateway;

use Generator;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEvent;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\TextEnd;
use Laravel\Ai\Streaming\Events\TextStart;
use Laravel\Ai\Streaming\Events\ToolCall as ToolCallEvent;

// Ported from laravel/ai v0.11 HandlesTextStreaming; contract test: ReasoningGatewayTest.
class ReasoningOpenAiCompatibleGateway extends OpenAiCompatibleGateway
{
    /**
     * Process a Chat Completions streaming response for a single turn and yield Laravel stream events.
     *
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    #[\Override]
    protected function processTextStream(
        string $invocationId,
        Provider $provider,
        string $model,
        $streamBody,
    ): Generator {
        return yield from $this->streamBody(
            $streamBody,
            $invocationId,
            $this->generateEventId(),
            $provider,
            $model,
        );
    }

    /**
     * Parse a Chat Completions stream body, emitting reasoning events for `reasoning_content` deltas.
     *
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    protected function streamBody(
        $streamBody,
        string $invocationId,
        string $messageId,
        ?Provider $provider = null,
        ?string $model = null,
    ): Generator {
        $streamStartEmitted = false;
        $textStartEmitted = false;
        $reasoningStartEmitted = false;
        $reasoningEndEmitted = false;
        $reasoningId = null;
        $currentText = '';
        $toolCalls = [];
        $pendingToolCalls = [];
        $usage = null;
        $finishReason = null;
        $responseModel = $model ?? '';

        foreach ($this->parseServerSentEvents($streamBody) as $data) {
            if (isset($data['error'])) {
                yield (new Error(
                    $this->generateEventId(),
                    $data['error']['code'] ?? 'unknown_error',
                    $data['error']['message'] ?? 'Unknown error',
                    false,
                    time(),
                ))->withInvocationId($invocationId);

                return null;
            }

            $choice = $data['choices'][0] ?? null;

            if (! $choice) {
                if (isset($data['usage'])) {
                    $usage = $this->extractUsage($data);
                }

                continue;
            }

            $delta = $choice['delta'] ?? [];

            if (! $streamStartEmitted) {
                $streamStartEmitted = true;
                $responseModel = $data['model'] ?? $model ?? '';

                yield (new StreamStart(
                    $this->generateEventId(),
                    $provider?->name() ?? '',
                    $data['model'] ?? $model ?? '',
                    time(),
                ))->withInvocationId($invocationId);
            }

            if (isset($delta['reasoning_content']) && $delta['reasoning_content'] !== '') {
                if (! $reasoningStartEmitted) {
                    $reasoningStartEmitted = true;
                    $reasoningId = $this->generateEventId();

                    yield (new ReasoningStart(
                        $this->generateEventId(),
                        $reasoningId,
                        time(),
                    ))->withInvocationId($invocationId);
                }

                yield (new ReasoningDelta(
                    $this->generateEventId(),
                    $reasoningId,
                    $delta['reasoning_content'],
                    time(),
                ))->withInvocationId($invocationId);
            }

            if (isset($delta['content']) && $delta['content'] !== '') {
                if ($reasoningStartEmitted && ! $reasoningEndEmitted) {
                    $reasoningEndEmitted = true;

                    yield (new ReasoningEnd(
                        $this->generateEventId(),
                        $reasoningId,
                        time(),
                    ))->withInvocationId($invocationId);
                }

                if (! $textStartEmitted) {
                    $textStartEmitted = true;

                    yield (new TextStart(
                        $this->generateEventId(),
                        $messageId,
                        time(),
                    ))->withInvocationId($invocationId);
                }

                $currentText .= $delta['content'];

                yield (new TextDelta(
                    $this->generateEventId(),
                    $messageId,
                    $delta['content'],
                    time(),
                ))->withInvocationId($invocationId);
            }

            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tcDelta) {
                    $idx = $tcDelta['index'];

                    if (! isset($pendingToolCalls[$idx])) {
                        $pendingToolCalls[$idx] = [
                            'id' => $tcDelta['id'] ?? '',
                            'name' => $tcDelta['function']['name'] ?? '',
                            'arguments' => '',
                        ];
                    }

                    if (isset($tcDelta['function']['arguments'])) {
                        $pendingToolCalls[$idx]['arguments'] .= $tcDelta['function']['arguments'];
                    }
                }
            }

            if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                $finishReason = $choice['finish_reason'];
            }

            if (isset($data['usage'])) {
                $usage = $this->extractUsage($data);
            }
        }

        if ($reasoningStartEmitted && ! $reasoningEndEmitted) {
            yield (new ReasoningEnd(
                $this->generateEventId(),
                $reasoningId,
                time(),
            ))->withInvocationId($invocationId);
        }

        if ($textStartEmitted) {
            yield (new TextEnd(
                $this->generateEventId(),
                $messageId,
                time(),
            ))->withInvocationId($invocationId);
        }

        if (filled($pendingToolCalls) && $finishReason === 'tool_calls') {
            $toolCalls = $this->mapStreamToolCalls($pendingToolCalls);

            foreach ($toolCalls as $toolCall) {
                yield (new ToolCallEvent(
                    $this->generateEventId(),
                    $toolCall,
                    time(),
                ))->withInvocationId($invocationId);
            }
        }

        return new StepResponse(
            text: $currentText,
            toolCalls: $toolCalls,
            finishReason: $this->extractFinishReason(['finish_reason' => $finishReason ?? '']),
            usage: $usage ?? new Usage(0, 0),
            meta: new Meta($provider?->name() ?? '', $responseModel),
        );
    }
}
