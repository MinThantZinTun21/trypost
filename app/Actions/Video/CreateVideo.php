<?php

declare(strict_types=1);

namespace App\Actions\Video;

use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Media\DirectAssetReceiver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVideo
{
    /**
     * Creates the Video and attaches the finished upload as its file; a
     * refused upload leaves no Video behind.
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, User $user, string $name, string $uploadId): Video
    {
        return DB::transaction(function () use ($workspace, $user, $name, $uploadId): Video {
            $video = $workspace->videos()->create(['user_id' => $user->id, 'name' => $name]);

            app(DirectAssetReceiver::class)->completeVideo($video, $user, $uploadId);

            return $video;
        });
    }
}
