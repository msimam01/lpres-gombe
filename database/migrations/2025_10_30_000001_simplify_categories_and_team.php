<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplifies manual content management:
     *
     * - Categories (Project/Team) stop being standalone public content: their
     *   active/inactive status and manual display order go away; ordering is
     *   alphabetical by name.
     * - Team members drop email/phone/biography and the is_spc flag: ordinary
     *   members are photograph + name + designation + category, and the SPC
     *   is a dedicated editorial profile outside the member CRUD.
     * - Manual display-order columns retire from Projects, Value Chain
     *   Clusters, Visual Reports and Media (automatic ordering instead).
     *
     * The publication workflows are untouched.
     */
    public function up(): void
    {
        foreach (['projects', 'value_chain_clusters', 'visual_reports', 'media_items'] as $source) {
            $this->dropColumn($source, ['display_order']);
        }

        foreach (['project_categories', 'team_categories'] as $source) {
            $this->dropColumn($source, ['display_order', 'status']);
        }

        $this->dropColumn('team_members', ['is_spc', 'display_order', 'biography', 'email', 'phone']);
    }

    /**
     * Drop the given columns (and their indexes) from a table with SQLite
     * compatible step-by-step handling, then no-op anything already gone.
     */
    private function dropColumn(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($existing === []) {
            return;
        }

        // SQLite refuses a column drop while an index still references it;
        // drop those indexes first. MySQL drops them automatically.
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === null || in_array($index['name'], [$table.'_primary'], true)) {
                continue;
            }

            if (array_intersect($index['columns'], $existing) !== []) {
                DB::statement("DROP INDEX \"{$index['name']}\"");
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing) {
            $blueprint->dropColumn($existing);
        });
    }

    public function down(): void
    {
        foreach (['projects', 'value_chain_clusters', 'visual_reports', 'media_items', 'project_categories', 'team_categories'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'display_order')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedInteger('display_order')->default(0)->index());
            }
        }

        foreach (['project_categories', 'team_categories'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'status')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->boolean('status')->default(true)->index());
            }
        }

        if (Schema::hasTable('team_members')) {
            $this->restoreColumns('team_members', [
                ['is_spc' => fn (Blueprint $blueprint) => $blueprint->boolean('is_spc')->default(false)->index()],
                ['display_order' => fn (Blueprint $blueprint) => $blueprint->unsignedInteger('display_order')->default(0)->index()],
                ['biography' => fn (Blueprint $blueprint) => $blueprint->text('biography')->nullable()],
                ['email' => fn (Blueprint $blueprint) => $blueprint->string('email')->nullable()],
                ['phone' => fn (Blueprint $blueprint) => $blueprint->string('phone')->nullable()],
            ]);
        }
    }

    private function restoreColumns(string $table, array $definitions): void
    {
        foreach ($definitions as $key => $definers) {
            foreach ($definers as $column => $definer) {
                if (! Schema::hasColumn($table, $column)) {
                    Schema::table($table, $definer);
                }
            }
        }
    }
};
