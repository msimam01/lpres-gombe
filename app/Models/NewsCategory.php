<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A news category ("News", and any classification staff add later).
 *
 * Categories are internal classification, not standalone public content: like
 * Project and Team categories there is no visibility switch and no manual
 * ordering — listings are alphabetical by name.
 */
class NewsCategory extends Model
{
    /**
     * The one category every imported WordPress article belongs to. The audit
     * (report 07, C.3) maps three original terms — 21 `Latest News`, 67 `News`
     * and 1 `Uncategorized` — onto this single Laravel category.
     */
    public const NEWS_SLUG = 'news';

    public const NEWS_SOURCE_TERM_ID = 67;

    protected $fillable = ['name', 'slug', 'description', 'wp_term_id'];

    protected function casts(): array
    {
        return ['wp_term_id' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'news_category');
    }
}
