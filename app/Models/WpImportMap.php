<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links one WordPress source record to the Laravel record it produced.
 *
 * The importer routes every write through this table, which is what allows it
 * to be re-run safely: a source record that is already mapped is updated only
 * when the source changed *and* the target has not been edited since import.
 *
 * `target_type` holds the target model class name (there is no morph map in
 * this application), so `target()` resolves the imported record directly.
 *
 * `source_metadata` preserves WordPress provenance that has no Laravel column
 * (category term IDs and names, author, original slug, attachment ids).
 */
class WpImportMap extends Model
{
    protected $table = 'wp_import_map';

    protected $fillable = [
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'source_hash',
        'target_hash',
        'source_metadata',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'target_id' => 'integer',
            'source_metadata' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * The Laravel record this mapping produced.
     */
    public function target(): MorphTo
    {
        return $this->morphTo('target', 'target_type', 'target_id');
    }

    public function scopeForSource(Builder $query, string $sourceType, int|string $sourceId): Builder
    {
        return $query
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId);
    }

    /**
     * Mappings whose target record has been written.
     */
    public function scopeImported(Builder $query): Builder
    {
        return $query->whereNotNull('imported_at');
    }

    /**
     * Mappings reserved but not yet written.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('imported_at');
    }

    public static function lookup(string $sourceType, int|string $sourceId): ?self
    {
        return static::query()->forSource($sourceType, $sourceId)->first();
    }

    public function isImported(): bool
    {
        return $this->imported_at !== null;
    }

    /**
     * Whether the WordPress record differs from what was imported.
     *
     * Unknown hashes (never imported, or mapping created before this
     * infrastructure existed) are treated as "not changed" so a missing
     * fingerprint can never trigger an overwrite on its own.
     */
    public function hasSourceChanged(?string $hash): bool
    {
        if ($this->source_hash === null || $hash === null) {
            return false;
        }

        return ! hash_equals($this->source_hash, $hash);
    }

    /**
     * Whether the Laravel record differs from what the importer wrote.
     *
     * A mapping without a `target_hash` cannot prove the target is untouched,
     * so it reports "changed" (the conservative answer): the importer must not
     * overwrite a record whose provenance it cannot verify.
     */
    public function hasTargetChanged(?string $hash): bool
    {
        if ($this->target_hash === null || $hash === null) {
            return true;
        }

        return ! hash_equals($this->target_hash, $hash);
    }

    /**
     * One value from the stored source provenance (category terms, author, ...).
     */
    public function sourceMeta(string $key, mixed $default = null): mixed
    {
        return ($this->source_metadata ?? [])[$key] ?? $default;
    }
}
