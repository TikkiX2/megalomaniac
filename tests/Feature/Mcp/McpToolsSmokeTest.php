<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

uses(RefreshDatabase::class);

it('every registered tool responds without internal errors', function () {
    $user = User::factory()->create();

    $tools = (new ReflectionClass(MegalomaniacServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(19);

    foreach ($tools as $tool) {
        $response = MegalomaniacServer::actingAs($user)->tool($tool, ['action' => 'noop']);

        $response->assertDontSee([
            'Cannot use object',
            'TypeError',
            'ErrorException',
            'Undefined property',
            'Undefined array key',
        ]);

        $isReadOnly = (new ReflectionClass($tool))->getAttributes(IsReadOnly::class) !== [];

        if ($isReadOnly) {
            $response->assertOk();
        }
    }
});
