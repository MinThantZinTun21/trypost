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
        DB::table('posts')->where('created_via', 'repurpose')->update(['created_via' => 'web']);

        if (Schema::hasColumn('posts', 'repurpose_item_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropForeign(['repurpose_item_id']);
                $table->dropColumn('repurpose_item_id');
            });
        }

        Schema::dropIfExists('repurpose_items');
        Schema::dropIfExists('repurposes');
    }

    public function down(): void
    {
        // Irreversible: Repurpose is gone for good.
    }
};
