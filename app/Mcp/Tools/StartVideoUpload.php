<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Requests\App\Mobile\StartVideoUploadRequest;
use App\Services\Media\ChunkedCloudUploader;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('start_video_upload')]
#[Description('Starts uploading one Video file from the Owner\'s computer straight to storage, for example a long talk to play in a Live stream. Returns an upload_id and a signed PUT URL valid for one hour, with a ready curl command: replace the file path in it, run it, then call create_video with the upload_id and a name.')]
class StartVideoUpload extends Tool
{
    /**
     * Same file name and size checks as the Mobile app's Video upload.
     */
    public function handle(Request $request, DirectAssetReceiver $receiver): Response|ResponseFactory
    {
        $request->merge(['file_name' => Str::lower((string) $request->get('file_name', ''))]);

        $rules = new StartVideoUploadRequest;
        $validated = $request->validate($rules->rules(), $rules->messages());

        $fileName = (string) data_get($validated, 'file_name');
        $uploadId = Str::uuid()->toString();
        $upload = $receiver->start($request->user(), $fileName, $uploadId);

        if (! data_get($upload, 'direct')) {
            return Response::error(__('mcp.upload.not_object_storage'));
        }

        $url = (string) data_get($upload, 'url');
        $headers = (array) data_get($upload, 'headers', []);

        return Response::structured([
            'upload_id' => $uploadId,
            'method' => 'PUT',
            'url' => $url,
            'headers' => $headers,
            'expires_in_seconds' => ChunkedCloudUploader::UPLOAD_URL_TTL_SECONDS,
            'curl' => StartMediaUpload::curlCommand($fileName, $url, $headers),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_name' => $schema->string()->description('The video file\'s name with its extension, e.g. talk.mp4.')->required(),
            'size' => $schema->integer()->description('The file\'s size in bytes.')->required(),
        ];
    }
}
