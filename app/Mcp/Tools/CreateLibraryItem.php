<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\LibraryItem\CreateLibraryItem as CreateLibraryItemAction;
use App\Enums\Post\CreatedVia;
use App\Mcp\Tools\Concerns\HandlesLibraryItems;
use App\Support\LibraryItemRules;
use App\Support\TikTokText;
use App\Support\YouTubeDescription;
use App\Support\YouTubeTags;
use App\Support\YouTubeTitle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_library_item')]
#[Description('Saves ready-made content to the Owner\'s Content library, to schedule later with schedule_library_item. Upload the files first with start_media_upload and complete_media_upload, then pass their ids. Give each Platform its text: the Facebook text, the TikTok caption, and the YouTube title, description and tags.')]
class CreateLibraryItem extends Tool
{
    use HandlesLibraryItems;

    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->resolveCurrentWorkspace();

        $validated = $request->validate(
            LibraryItemRules::rules($workspace->getMedia('assets')->get()->keyBy('id')),
            [...LibraryItemRules::messages(), 'media_ids.*.in' => __('mcp.post.media_not_found')],
            LibraryItemRules::attributes(),
        );

        $libraryItem = CreateLibraryItemAction::execute($workspace, $validated, CreatedVia::Mcp);

        return Response::structured([
            'library_item' => $this->libraryItemPayload($libraryItem),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $mediaMax = LibraryItemRules::MEDIA_MAX;
        $captionMax = TikTokText::CAPTION_MAX;
        $youtubeTitleMax = YouTubeTitle::MAX_CHARACTERS;
        $youtubeDescriptionMax = YouTubeDescription::MAX_BYTES;
        $tagsMax = YouTubeTags::MAX_CHARACTERS;

        return [
            'name' => $schema->string()->max(LibraryItemRules::NAME_MAX)->description('A short name the Owner will recognise in the library.')->required(),
            'media_ids' => $schema->array()->items($schema->string())->description("Media ids from complete_media_upload, in order: one video, or up to {$mediaMax} images. No documents."),
            'facebook_text' => $schema->string()->description('The Facebook Post text.'),
            'tiktok_caption' => $schema->string()->description("The TikTok caption with #hashtags (max {$captionMax}). A TikTok Photo uses it as its description."),
            'youtube_title' => $schema->string()->description("The YouTube Short title (max {$youtubeTitleMax}, no < or >)."),
            'youtube_description' => $schema->string()->description("The YouTube Short description (max {$youtubeDescriptionMax} bytes)."),
            'youtube_tags' => $schema->array()->items($schema->string())->description("YouTube Short tags, without #. At most {$tagsMax} characters in total, counting the commas between tags and the quotes YouTube adds around a tag with a space."),
        ];
    }
}
