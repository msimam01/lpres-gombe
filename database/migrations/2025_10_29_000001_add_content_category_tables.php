<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds managed category modules for Projects and Team Members. Existing
     * free-text category values are converted into real category rows so no
     * data is discarded, then the legacy string column is dropped.
     */
    public function up(): void
    {
        foreach ([
            ['table' => 'project_categories', 'source' => 'projects',
                'column' => 'project_category_id', 'is_mysql' => $this->connectionUsesMysql()],
            ['table' => 'team_categories', 'source' => 'team_members',
                'column' => 'team_category_id', 'is_mysql' => $this->connectionUsesMysql()],
        ] as $spec) {
            $this->migrateSource($spec);
        }
    }

    private function migrateSource(array $spec): void
    {
        [$source, $categories, $column] = [$spec['source'], $spec['table'], $spec['column']];

        if (! Schema::hasTable($source) || Schema::hasColumn($source, $column)) {
            return;
        }

        // Create the category table if a retry left the migration half done.
        if (! Schema::hasTable($categories)) {
            Schema::create($categories, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('display_order')->default(0)->index();
                $table->boolean('status')->default(true)->index();
                $table->timestamps();
            });
        }

        // 1. Simple column add — no foreign key in the same statement, which
        //    SQLite (unlike MySQL) cannot add through ALTER TABLE.
        Schema::table($source, function (Blueprint $table) use ($column, $spec) {
            $definition = $table->unsignedBigInteger($column)->nullable();

            if ($spec['is_mysql']) {
                $definition->after('category');
            }
        });

        // 2. Backfill from the legacy free-text column.
        if (Schema::hasColumn($source, 'category')) {
            $labels = DB::table($source)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category');

            $labelToId = [];
            foreach ($labels as $label) {
                $slug = Str::slug((string) $label)
                    ?: Str::lower(Str::random(8));
                $unique = $slug;
                $attempt = 2;
                while (DB::table($categories)->where('slug', $unique)->exists()) {
                    $unique = $slug.'-'.$attempt++;
                }

                $id = DB::table($categories)->insertGetId([
                    'name' => $label,
                    'slug' => $unique,
                    'display_order' => 0,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $labelToId[$label] = $id;
            }

            foreach ($labelToId as $label => $id) {
                DB::table($source)
                    ->where('category', $label)
                    ->update([$column => $id]);
            }

            // 3. Drop indexes that include the legacy column first: SQLite
            //    refuses the column drop while an index still references it.
            foreach (Schema::getIndexes($source) as $index) {
                if (in_array('category', $index['columns'], true) && $index['name'] !== null) {
                    DB::statement("DROP INDEX \"{$index['name']}\"");
                }
            }

            // 4. Drop the legacy column as its own step; on SQLite this is a
            //    full table rebuild, so it must not be mixed with other
            //    constraints in the same blueprint.
            Schema::table($source, fn (Blueprint $table) => $table->dropColumn('category'));
        }

        // 4. Attach the FK on its own blueprint after the legacy column is
        //    gone (SQLite rebuilds the table for constraint writes).
        Schema::table($source, function (Blueprint $table) use ($column, $categories) {
            $table->foreign($column, "{$categories}_fk")
                ->references('id')
                ->on($categories)
                ->nullOnDelete();
        });
    }

    /**
     * MySQL keeps column order (`->after('category')`), SQLite ignores it.
     */
    private function connectionUsesMysql(): bool
    {
        return DB::connection($this->connection ?? config('database.default'))
            ->getDriverName() === 'mysql';
    }

    public function down(): void
    {
        foreach ([
            ['source' => 'projects', 'column' => 'project_category_id', 'table' => 'project_categories'],
            ['source' => 'team_members', 'column' => 'team_category_id', 'table' => 'team_categories'],
        ] as [$source, $column, $categories]) {
            if (Schema::hasColumn($source, $column)) {
                Schema::table($source, function (Blueprint $table) use ($column, $categories) {
                    $table->dropForeign("{$categories}_fk");
                    $table->dropColumn($column);
                });
            }

            // Restore the legacy string column.
            Schema::table($source, fn (Blueprint $table) => $table->string('category')->nullable()->index());
        }

        Schema::dropIfExists('team_categories');
        Schema::dropIfExists('project_categories');
    }
};
