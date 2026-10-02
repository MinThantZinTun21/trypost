<?php

declare(strict_types=1);

test('the login screen receives the configured legal links', function () {
    config()->set('trypost.legal.terms_url', 'https://example.test/terms');
    config()->set('trypost.legal.privacy_url', 'https://example.test/privacy');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('legal.terms', 'https://example.test/terms')
            ->where('legal.privacy', 'https://example.test/privacy')
        );
});

test('a self-hosted install can point the legal links at its own documents', function () {
    config()->set('trypost.legal.terms_url', 'https://acme.test/legal/tos');
    config()->set('trypost.legal.privacy_url', 'https://acme.test/legal/privacy');

    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page
            ->where('legal.terms', 'https://acme.test/legal/tos')
            ->where('legal.privacy', 'https://acme.test/legal/privacy')
        );
});

test('the legal sentence carries a url placeholder rather than a hardcoded host', function (string $locale) {
    $sentence = require base_path("lang/{$locale}/auth.php");

    expect($sentence['legal'])
        ->toContain(':terms_url')
        ->toContain(':privacy_url')
        ->not->toContain('trypost.it');
})->with(array_map(
    fn (string $path) => basename(dirname($path)),
    glob(dirname(__DIR__, 3).'/lang/*/auth.php'),
));
