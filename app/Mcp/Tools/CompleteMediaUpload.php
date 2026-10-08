<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Requests\App\Asset\CompleteDirectAssetRequest;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('complete_media_upload')]
#[Description('Registers a file uploaded with start_media_upload\'s curl command and returns the media item. Pass its id in create_post\'s media_ids. Size and type are checked like the composer\'s uploads.')]
class CompleteMediaUpload extends Tool
{
    public function handle(Request $request, DirectAssetReceiver $receiver): ResponseFactory
    {
        $validated = $request->validate((new CompleteDirectAssetRequest)->rules());
        $duration = data_get($validated, 'duration');

        $media = $receiver->complete(
            $request->user()->resolveCurrentWorkspace(),
            $request->user(),
            (string) data_get($validated, 'upload_id'),
            $duration === null ? null : (float) $duration,
        )->media;

        return Response::structured([
            'media' => [
                'id' => $media->id,
                'type' => $media->type->value,
                'url' => $media->url,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'duration' => data_get($media->meta, 'duration'),
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'upload_id' => $schema->string()->description('The upload_id from start_media_upload.')->required(),
            'duration' => $schema->number()->description('Optional: the video\'s length in seconds, when known.'),
        ];
    }
}
