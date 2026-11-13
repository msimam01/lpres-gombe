<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A downloadable document or an external (Google Drive) link, managed by
 * administrators. Uploaded files live on the private disk; the public site
 * only ever sees a controlled download route or the external URL.
 */
class Document extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'file_path', 'url', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Whether this record points at an uploaded file rather than a URL. */
    public function hasFile(): bool
    {
        return filled($this->file_path);
    }
}
