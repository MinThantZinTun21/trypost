<?php

declare(strict_types=1);

/**
 * Content markers for destructive copy. These markers catch copy that still has
 * the key but omits the invited-members / conditional-delete warning.
 *
 * @var array<string, string>
 */
$accountDeleteInvitedMemberMarkers = [
    'en' => 'invited members',
];

/**
 * @var array<string, string>
 */
$workspaceDeleteConditionalMemberMarkers = [
    'en' => 'without another TryPost workspace',
];

test('workspace delete members warning describes conditional permanent deletion', function () {
    $warning = trans_choice('settings.workspace.delete_members_warning', 1, ['count' => 1]);

    expect($warning)
        ->toContain('lose access')
        ->toContain('without another TryPost workspace')
        ->toContain('permanently deleted')
        ->not->toContain('personal account');
});

test('account delete warning mentions invited members are permanently deleted', function () {
    expect(__('settings.delete_account.warning_message'))
        ->toContain('invited members')
        ->toContain('permanently deleted');

    expect(__('settings.delete_account.modal_description_password'))
        ->toContain('invited members')
        ->toContain('permanently deleted');
});

test('account delete modals mention invited members', function (string $locale, string $needle) {
    expect(__('settings.delete_account.modal_description_password', [], $locale))
        ->toContain($needle);

    expect(__('settings.delete_account.modal_description_email', ['email' => 'x@y.z'], $locale))
        ->toContain($needle);

    expect(__('settings.delete_account.warning_message', [], $locale))
        ->toContain($needle);
})->with(
    collect($accountDeleteInvitedMemberMarkers)
        ->map(fn (string $needle, string $locale): array => [$locale, $needle])
        ->all()
);

test('workspace delete members warning is conditional', function (string $locale, string $needle) {
    $warning = trans_choice(
        'settings.workspace.delete_members_warning',
        2,
        ['count' => 2],
        $locale,
    );

    expect($warning)->toContain($needle);
})->with(
    collect($workspaceDeleteConditionalMemberMarkers)
        ->map(fn (string $needle, string $locale): array => [$locale, $needle])
        ->all()
);
