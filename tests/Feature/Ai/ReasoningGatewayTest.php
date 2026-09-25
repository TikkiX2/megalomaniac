<?php

use App\Ai\Gateway\ReasoningOpenAiCompatibleGateway;
use App\Ai\Providers\ReasoningOpenAiCompatibleProvider;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\AiManager;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\TextDelta;

test('reasoning compatible provider is registered as a driver', function () {
    expect(config('ai.providers.user.driver'))->toBe('reasoning-compatible');

    $provider = app(AiManager::class)->instance('user');

    expect($provider)->toBeInstanceOf(ReasoningOpenAiCompatibleProvider::class);
});

test('gateway emits reasoning deltas before text deltas', function () {
    $sse = implode("\n\n", [
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => 'Pienso'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['reasoning_content' => ' mucho'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => ['content' => 'Hola'], 'finish_reason' => null]]]),
        'data: '.json_encode(['model' => 'qa', 'choices' => [['delta' => [], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
    ])."\n\n";

    $stream = Utils::streamFor($sse);
    $gateway = new class(app(Dispatcher::class)) extends ReasoningOpenAiCompatibleGateway
    {
        public function exposeStream($body): Generator
        {
            return $this->streamBody($body, 'inv-1', 'msg-1');
        }
    };

    $events = iterator_to_array($gateway->exposeStream($stream));

    $reasoning = implode('', array_map(fn ($e) => $e instanceof ReasoningDelta ? $e->delta : '', $events));
    $text = implode('', array_map(fn ($e) => $e instanceof TextDelta ? $e->delta : '', $events));

    expect($reasoning)->toBe('Pienso mucho');
    expect($text)->toBe('Hola');
});
