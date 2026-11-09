<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator-edit protection and source provenance for imports.
 *
 * `source_hash` (Phase 1) answers "did the WordPress record change?".
 * `target_hash` answers the other half — "has the Laravel record been edited
 * since we wrote it?" — so a re-run can never overwrite an administrator's work
 * without the explicit --force-overwrite-admin-edits flag.
 *
 * `source_metadata` keeps provenance that has no Laravel column: the original
 * WordPress category term IDs and names attached to an imported article, its
 * original author and slug, its thumbnail id and the attachments embedded in
 * its body. Storing it on the mapping row means it survives re-runs and is
 * queryable, instead of living only in console output.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_import_map', function (Blueprint $table) {
            if (! Schema::hasColumn('wp_import_map', 'target_hash')) {
                $table->string('target_hash', 64)->nullable()->after('source_hash');
            }

            if (! Schema::hasColumn('wp_import_map', 'source_metadata')) {
                $table->json('source_metadata')->nullable()->after('target_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wp_import_map', function (Blueprint $table) {
            foreach (['source_metadata', 'target_hash'] as $column) {
                if (Schema::hasColumn('wp_import_map', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
