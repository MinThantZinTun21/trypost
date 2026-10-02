<?php

declare(strict_types=1);

use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\ImageCardTemplate;
use App\Ai\Templates\TweetCardImageTemplate;
use App\Ai\Templates\TweetCardTemplate;
use App\Enums\Ai\ContentStyle;

test('registry resolves keys and defaults to image_card', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->keys())->toBe(['image_card', 'tweet_card', 'tweet_card_image'])
        ->and($registry->find('image_card'))->toBeInstanceOf(ImageCardTemplate::class)
        ->and($registry->find('tweet_card_image'))->toBeInstanceOf(TweetCardImageTemplate::class)
        ->and($registry->default()->key())->toBe('image_card');
});

test('registry find accepts a ContentStyle enum', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->find(ContentStyle::TweetCard))->toBeInstanceOf(TweetCardTemplate::class)
        ->and($registry->find(ContentStyle::ImageCard))->toBeInstanceOf(ImageCardTemplate::class)
        ->and($registry->find(ContentStyle::TweetCardImage))->toBeInstanceOf(TweetCardImageTemplate::class);
});

test('registry find accepts a string key', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->find('tweet_card'))->toBeInstanceOf(TweetCardTemplate::class);
});

test('registry styles returns all ContentStyle cases', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->styles())->toBe(ContentStyle::cases());
});

test('registry throws on unknown key', function () {
    expect(fn () => app(AiTemplateRegistry::class)->find('nope'))->toThrow(InvalidArgumentException::class);
});

test('registry does not contain carousel as a standalone style', function () {
    $registry = app(AiTemplateRegistry::class);
    expect($registry->keys())->not->toContain('carousel');
});
