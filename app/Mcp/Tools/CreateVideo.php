<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Video\CreateVideo as CreateVideoAction;
use App\Http\Requests\App\Mobile\StoreVideoRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_video')]
#[Description('Registers a file uploaded with start_video_upload\'s curl command as a Video with a name, and returns it. It then shows in list_videos and can be played in a Live stream.')]
class CreateVideo extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate((new StoreVideoRequest)->rules());

        $video = CreateVideoAction::execute(
            $request->user()->resolveCurrentWorkspace(),
            $request->user(),
            (string) data_get($validated, 'name'),
            (string) data_get($validated, 'upload_id'),
        );

        return Response::structured(['video' => ListVideos::summary($video->load('file'))]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'upload_id' => $schema->string()->description('The upload_id from start_video_upload.')->required(),
            'name' => $schema->string()->description('A name for the Video, e.g. the talk\'s title.')->required(),
        ];
    }
}
