<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

class PurgeUserAccess
{
    /**
     * Delete user media rows.
     *
     * @return list<string> media paths for DeleteOrphanedMediaFiles after commit
     */
    public static function execute(User $user): array
    {
        $userMediaQuery = Media::query()
            ->where('mediable_type', Relation::getMorphAlias(User::class))
            ->where('mediable_id', $user->id);

        /** @var list<string> $mediaPaths */
        $mediaPaths = $userMediaQuery->pluck('path')->all();
        $userMediaQuery->delete();

        return $mediaPaths;
    }
}
