<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress import tracking.
 *
 * One row per WordPress source record (post, page, attachment, term, ...) that
 * has been — or is about to be — imported. Keeping the mapping in a dedicated
 * table means no existing content table gains a WordPress-specific column: the
 * public schema stays exactly as it is, while the importer can still answer
 * "was this record already imported, from which source, and has the source
 * changed since?".
 *
 * `source_hash` stores the fingerprint of the imported source fields, so a
 * later run can distinguish an untouched import from one an administrator has
 * since edited (see App\Migration\SourceHash).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wp_import_map')) {
            return;
        }

        Schema::create('wp_import_map', function (Blueprint $table) {
            $table->id();

            // WordPress side: e.g. 'post', 'page', 'project', 'attachment'.
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');

            // Laravel side: model class and primary key of the imported record.
            // Nullable so a mapping can be reserved before its target exists.
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();

            // Fingerprint of the imported source fields.
            $table->string('source_hash', 64)->nullable();

            // Set only once a record has actually been written.
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();

            // One mapping per WordPress record — the basis of idempotent re-runs.
            $table->unique(['source_type', 'source_id']);

            // Reverse lookup: "which source produced this Laravel record?".
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_import_map');
    }
};
