<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Children first: the pivot and the logs hold foreign keys to the tables below.
        Schema::dropIfExists('post_workspace_label');
        Schema::dropIfExists('workspace_labels');
        Schema::dropIfExists('workspace_signatures');
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('webhooks');
    }

    public function down(): void
    {
        // Irreversible: Signatures, Labels and outgoing webhooks are gone for good.
    }
};
