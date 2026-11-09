<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * News categories.
 *
 * News articles are classified through a pivot rather than a single
 * `category_id` column: the WordPress source associates one article with
 * several terms (Latest News, News, Uncategorized) and the audit requires every
 * genuine article to be reachable through one Laravel `News` category without
 * discarding the original classification.
 *
 * Category rows follow the existing Project/Team category convention: a name,
 * a unique slug and an optional description — no visibility switch, no manual
 * ordering.
 *
 * `wp_term_id` records which WordPress term a category corresponds to
 * (audit report 07, C.3: exactly one `News` row, `wp_term_id = 67`). It is
 * indexed but not unique on purpose: the audit also requires several original
 * WordPress terms (21 `Latest News`, 67 `News`, 1 `Uncategorized`) to be
 * representable as one Laravel category, so a term id identifies a source term
 * rather than owning a category.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('news_categories')) {
            Schema::create('news_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('wp_term_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('news_category')) {
            Schema::create('news_category', function (Blueprint $table) {
                $table->id();
                $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
                $table->foreignId('news_category_id')->constrained('news_categories')->cascadeOnDelete();
                $table->timestamps();

                // An article can hold a given category only once.
                $table->unique(['news_id', 'news_category_id']);
                $table->index('news_category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_category');
        Schema::dropIfExists('news_categories');
    }
};
