<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config([
        'trypost.telegram.bot_token' => 'test-bot-token',
        'trypost.telegram.channel_id' => '@trypost_mirror',
    ]);
});

test('it sends a test message to the channel', function () {
    $api = config('trypost.telegram.api');
    Http::fake(["{$api}/*" => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $this->artisan('telegram:test')
        ->expectsOutput('Test message sent to @trypost_mirror.')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === "{$api}/bottest-bot-token/sendMessage"
        && $request['chat_id'] === '@trypost_mirror'
        && $request['text'] === __('telegram.test_message', ['app' => config('app.name')]));
});

test('it explains why Telegram refused the message', function (int $status, string $description, string $hint) {
    $api = config('trypost.telegram.api');
    Http::fake(["{$api}/*" => Http::response(['ok' => false, 'description' => $description], $status)]);

    $this->artisan('telegram:test')
        ->expectsOutput("Telegram refused the message: {$description}")
        ->expectsOutputToContain($hint)
        ->assertFailed();
})->with([
    'bot not an admin' => [Response::HTTP_FORBIDDEN, 'Forbidden: bot is not a member of the channel chat', 'administrator'],
    'wrong channel' => [Response::HTTP_BAD_REQUEST, 'Bad Request: chat not found', 'TELEGRAM_CHANNEL_ID'],
    'wrong token' => [Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'TELEGRAM_BOT_TOKEN'],
]);

test('it says how to turn the mirror on when it is off', function () {
    config(['trypost.telegram.bot_token' => null]);
    Http::fake();

    $this->artisan('telegram:test')
        ->expectsOutputToContain('TELEGRAM_BOT_TOKEN')
        ->assertFailed();

    Http::assertNothingSent();
});
