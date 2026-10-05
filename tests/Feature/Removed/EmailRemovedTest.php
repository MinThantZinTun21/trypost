<?php

declare(strict_types=1);

use App\Enums\Notification\Type;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('notification preference endpoints are gone', function (string $method) {
    $user = User::factory()->create();

    $this->actingAs($user)->json($method, '/settings/profile/notifications')->assertNotFound();
})->with(['GET', 'PUT']);

test('the app ships no mailables, mail views or maizzle build', function () {
    expect(is_dir(app_path('Mail')))->toBeFalse()
        ->and(is_dir(resource_path('views/mail')))->toBeFalse()
        ->and(is_dir(base_path('maizzle')))->toBeFalse();
});

test('notification preferences are not stored', function () {
    expect(Schema::hasTable('notification_preferences'))->toBeFalse();
});

test('only the three in-app notification types remain', function () {
    expect(array_map(fn (Type $type): string => $type->value, Type::cases()))
        ->toBe(['post_failed', 'account_disconnected', 'post_at_risk']);
});

test('no mail provider package is installed and mail defaults to the log mailer', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect(array_keys(data_get($composer, 'require', [])))->not->toContain('sendkit/sendkit-laravel')
        ->and(file_get_contents(config_path('mail.php')))->toContain("env('MAIL_MAILER', 'log')")
        ->not->toContain('sendkit');
});
