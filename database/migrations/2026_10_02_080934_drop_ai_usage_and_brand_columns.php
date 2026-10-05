<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $brandColumns = [
        'brand_website',
        'brand_description',
        'brand_voice_traits',
        'brand_color',
        'background_color',
        'text_color',
        'brand_font',
        'image_style',
        'content_language',
    ];

    public function up(): void
    {
        Schema::dropIfExists('workspace_ai_usages');

        // "Your post is ready" was only sent by the AI post wizard.
        DB::table('notifications')->where('type', 'post_ready')->delete();

        $columns = array_values(array_filter(
            $this->brandColumns,
            fn (string $column): bool => Schema::hasColumn('workspaces', $column),
        ));

        if ($columns !== []) {
            Schema::table('workspaces', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        // Irreversible: the AI copilot and brand profile are gone for good.
    }
};
