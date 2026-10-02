<?php

declare(strict_types=1);

use App\Services\Social\BlueskyLexicon;

test('bluesky lexicon constants match their AT Protocol NSIDs', function (string $constant, string $nsid) {
    expect($constant)->toBe($nsid);
})->with([
    [BlueskyLexicon::RESOLVE_HANDLE, 'com.atproto.identity.resolveHandle'],
    [BlueskyLexicon::CREATE_RECORD, 'com.atproto.repo.createRecord'],
    [BlueskyLexicon::UPLOAD_BLOB, 'com.atproto.repo.uploadBlob'],
    [BlueskyLexicon::CREATE_SESSION, 'com.atproto.server.createSession'],
    [BlueskyLexicon::REFRESH_SESSION, 'com.atproto.server.refreshSession'],
    [BlueskyLexicon::FEED_POST, 'app.bsky.feed.post'],
    [BlueskyLexicon::GET_POSTS, 'app.bsky.feed.getPosts'],
    [BlueskyLexicon::GET_PROFILE, 'app.bsky.actor.getProfile'],
    [BlueskyLexicon::EMBED_IMAGES, 'app.bsky.embed.images'],
    [BlueskyLexicon::FACET_LINK, 'app.bsky.richtext.facet#link'],
    [BlueskyLexicon::FACET_MENTION, 'app.bsky.richtext.facet#mention'],
    [BlueskyLexicon::FACET_TAG, 'app.bsky.richtext.facet#tag'],
]);
