<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One image inside a gallery: the stored public-disk path in the gallery's
 * display order. Paths are shared references; the underlying upload is only
 * deleted when no other record references it.
 */
class GalleryImage extends Model
{
    protected $fillable = ['gallery_id', 'path', 'sort_index'];

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
