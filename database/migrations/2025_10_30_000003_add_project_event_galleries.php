<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects and Events gain a managed image gallery in addition to their
     * featured image: a JSON list of uploaded image paths shown on the
     * detail page. Storage keeps single-source-of-truth paths so replacing a
     * featured image never deletes a file still used in the gallery (see
     * AdminModuleController::releaseFile).
     */
    public function up(): void
    {
        foreach (['projects', 'events'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'gallery_image_paths')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->json('gallery_image_paths')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['projects', 'events'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'gallery_image_paths')) {
                continue;
            }

            foreach (Schema::getIndexes($table) as $index) {
                if (in_array('gallery_image_paths', $index['columns'], true) && $index['name'] !== null) {
                    DB::statement("DROP INDEX \"{$index['name']}\"");
                }
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('gallery_image_paths'));
        }
    }
};
