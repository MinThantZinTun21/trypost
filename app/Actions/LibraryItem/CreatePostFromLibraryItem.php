<?php

declare(strict_types=1);

namespace App\Actions\LibraryItem;

use App\Actions\Post\CreatePost;
use App\Enums\Post\CreatedVia;
use App\Enums\PostPlatform\ContentType;
use App\Models\LibraryItem;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePostFromLibraryItem
{
    /**
     * Create a Draft from a Library item for the chosen Social accounts, through
     * the composer's create action. The Facebook text is the content; each
     * account gets the Content type its Platform defaults to for the item's
     * media, with the item's texts for that type.
     *
     * @param  list<string>  $socialAccountIds
     */
    public static function execute(LibraryItem $libraryItem, User $user, array $socialAccountIds, CreatedVia $createdVia): Post
    {
        $workspace = $libraryItem->workspace;
        $mediaItems = $libraryItem->mediaItems;

        $platforms = $workspace->socialAccounts()
            ->active()
            ->whereIn('id', $socialAccountIds)
            ->get()
            ->map(function (SocialAccount $account) use ($libraryItem, $mediaItems): array {
                $contentType = ContentType::defaultForMedia($account->platform, $mediaItems);

                return [
                    'social_account_id' => $account->id,
                    'content_type' => $contentType->value,
                    'meta' => $libraryItem->textsFor($contentType),
                ];
            })
            ->all();

        return DB::transaction(function () use ($libraryItem, $user, $workspace, $platforms, $createdVia): Post {
            $post = CreatePost::execute($workspace, $user, [
                'content' => $libraryItem->facebook_text ?? '',
                'media' => $libraryItem->media ?? [],
                'created_via' => $createdVia,
                'platforms' => $platforms,
            ]);

            $post->update(['library_item_id' => $libraryItem->id]);

            return $post;
        });
    }
}
