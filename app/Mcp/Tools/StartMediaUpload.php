<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Requests\App\Asset\StoreDirectAssetRequest;
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

#[Name('start_media_upload')]
#[Description('Starts uploading one image, video or PDF from the Owner\'s computer straight to storage. Returns an upload_id and a signed PUT URL valid for one hour, with a ready curl command: replace the file path in it, run it, then call complete_media_upload with the upload_id.')]
class StartMediaUpload extends Tool
{
    /**
     * Same file name and size checks as the composer's direct upload.
     */
    public function handle(Request $request, DirectAssetReceiver $receiver): Response|ResponseFactory
    {
        $request->merge(['file_name' => Str::lower((string) $request->get('file_name', ''))]);

        $rules = (new StoreDirectAssetRequest)->rules();
        $validated = $request->validate([
            'file_name' => data_get($rules, 'file_name'),
            'size' => data_get($rules, 'total_size'),
        ], [
            'size.max' => data_get((new StoreDirectAssetRequest)->messages(), 'total_size.max'),
        ]);

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
            'expires_in_seconds' => 3600,
            'curl' => $this->curlCommand($fileName, $url, $headers),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file_name' => $schema->string()->description('The file\'s name with its extension, e.g. clip.mp4.')->required(),
            'size' => $schema->integer()->description('The file\'s size in bytes.')->required(),
        ];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function curlCommand(string $fileName, string $url, array $headers): string
    {
        $headerFlags = collect($headers)
            ->map(fn (string $value, string $name): string => '-H '.escapeshellarg("{$name}: {$value}"))
            ->implode(' ');

        return trim('curl --fail -X PUT -T '.escapeshellarg("/path/to/{$fileName}")." {$headerFlags} ".escapeshellarg($url));
    }
}
