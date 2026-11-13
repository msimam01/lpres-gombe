<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Project components gain a stable, slug-based public URL. Existing rows are
 * backfilled from their titles so homepage links keep working after upgrade;
 * no record content is changed otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_components', function (Blueprint $table) {
            // Nullable so a record saved without a slug can never break the
            // unique index; controllers generate and pin slugs from the title.
            $table->string('slug')->nullable()->unique()->after('title');
        });

        $used = [];

        foreach (DB::table('project_components')->orderBy('id')->get(['id', 'title']) as $row) {
            $base = Str::slug(Str::limit((string) $row->title, 120, '')) ?: 'component-'.$row->id;
            $slug = $base;
            $attempt = 2;

            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$attempt++;
            }

            $used[] = $slug;

            DB::table('project_components')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('project_components', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
