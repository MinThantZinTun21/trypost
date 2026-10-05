<?php

declare(strict_types=1);

namespace App\Http\Resources\App\HandleInertiaRequests;

use App\Models\Workspace;

class AuthWorkspaceResource
{
    /**
     * @return array{id: string, name: string, created_at: string}
     */
    public static function make(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'created_at' => $workspace->created_at->toIso8601String(),
        ];
    }
}
