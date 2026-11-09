<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordered inline images for a news article.
 *
 * 123 of the 132 genuine WordPress articles embed images in their body. `news`
 * previously had no home for them (unlike `projects` and `events`, which already
 * use an ordered JSON gallery), so the importer would have had to discard them.
 * Reusing the established `gallery_image_paths` JSON convention keeps the media
 * model identical across content types and preserves the author's order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('news', 'gallery_image_paths')) {
            return;
        }

        Schema::table('news', function (Blueprint $table) {
            $table->json('gallery_image_paths')->nullable()->after('featured_image_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('news', 'gallery_image_paths')) {
            return;
        }

        Schema::table('news', fn (Blueprint $table) => $table->dropColumn('gallery_image_paths'));
    }
};
