<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->string('open_graph_image_path')->nullable()->after('vertical_image_disk');
            $table->string('open_graph_image_disk')->nullable()->after('open_graph_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            $table->dropColumn(['open_graph_image_path', 'open_graph_image_disk']);
        });
    }
};
