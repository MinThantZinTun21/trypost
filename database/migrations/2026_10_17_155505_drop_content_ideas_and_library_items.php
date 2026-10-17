<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content ideas and the Content library were removed. Posts made from a
 * Library item keep their content and media; they only lose the link back.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('posts', 'library_item_id')) {
            Schema::table('posts', function (Blueprint $table) {
                $table->dropForeign(['library_item_id']);
                $table->dropColumn('library_item_id');
            });
        }

        Schema::dropIfExists('library_items');
        Schema::dropIfExists('content_ideas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('content_ideas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('title');
            $table->text('details')->nullable();
            $table->string('status')->default('new');
            $table->string('created_via')->default('web');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('library_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->json('media')->nullable();
            $table->text('facebook_text')->nullable();
            $table->text('tiktok_caption')->nullable();
            $table->string('youtube_title')->nullable();
            $table->text('youtube_description')->nullable();
            $table->json('youtube_tags')->nullable();
            $table->string('created_via')->default('web');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->uuid('library_item_id')->nullable()->after('workspace_id');

            $table->foreign('library_item_id')->references('id')->on('library_items')->nullOnDelete();
        });
    }
};
