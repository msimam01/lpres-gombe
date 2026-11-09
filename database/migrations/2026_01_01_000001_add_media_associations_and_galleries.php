<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media architecture refinement:
 *
 *  - media_items gains an optional association to one event/news/project
 *    record (a standalone item leaves both columns null). Existing rows keep
 *    every column (album stays in the schema even though the redesigned
 *    admin workflow no longer edits it).
 *  - galleries holds a titled, publishable image collection that may also be
 *    associated with an event/news/project record or stand alone.
 *  - gallery_images stores the individual images of a gallery in display
 *    order (no duplicate files: paths are shared references, released from
 *    disk only when nothing else references them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            if (! Schema::hasColumn('media_items', 'associated_type')) {
                $table->string('associated_type')->nullable()->index();
            }

            if (! Schema::hasColumn('media_items', 'associated_id')) {
                $table->unsignedBigInteger('associated_id')->nullable();
            }
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('associated_type')->nullable()->index();
            $table->unsignedBigInteger('associated_id')->nullable();
            $table->timestamps();
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_index')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('galleries');

        Schema::table('media_items', function (Blueprint $table) {
            foreach (['associated_type', 'associated_id'] as $column) {
                if (Schema::hasColumn('media_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
