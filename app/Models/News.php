<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A news article. Only `published` records are visible on the public site;
 * drafts are visible nowhere on the front end.
 *
 * `gallery_image_paths` holds images that appeared inside the article body, in
 * author order (the same ordered JSON convention `projects` and `events` use).
 * The featured image stays in its own column.
 */
class News extends Model
{
    protected $fillable = [
        'title', 'slug', 'featured_image_path', 'gallery_image_paths', 'excerpt', 'body', 'published_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'gallery_image_paths' => 'array',
        ];
    }

    /**
     * Records that are both marked published and past their publication date.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * Categories this article is classified under.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(NewsCategory::class, 'news_category')
            ->orderBy('news_categories.name');
    }

    /**
     * Inline body images, in author order, as a plain list of stored paths.
     *
     * @return list<string>
     */
    public function galleryPaths(): array
    {
        return array_values(array_filter((array) ($this->gallery_image_paths ?? [])));
    }

    /**
     * The excerpt is fully derived from the title (product requirement), so it
     * is never imported from WordPress or accepted from a form field. Kept here
     * so the admin controller and the importer share one rule.
     */
    public static function excerptFromTitle(string $title): string
    {
        return Str::limit(trim($title), 250, '');
    }
}
