<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('posts')->whereIn('created_via', ['api', 'mcp'])->update(['created_via' => 'web']);

        if (Schema::hasColumn('medias', 'upload_token')) {
            Schema::table('medias', function (Blueprint $table): void {
                $table->dropUnique(['upload_token']);
                $table->dropColumn('upload_token');
            });
        }

        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_clients');
    }

    public function down(): void
    {
        // Irreversible: the REST API, API keys, MCP server and Passport are gone for good.
    }
};
