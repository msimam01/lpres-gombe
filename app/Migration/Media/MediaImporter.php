<?php

namespace App\Migration\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Copies resolved WordPress media onto the configured public disk.
 *
 * Safety rules:
 *  - target paths are deterministic (`uploads/news/<manifest relative path>`)
 *    and every segment is sanitised, so a manifest value can never traverse
 *    outside the upload directory;
 *  - a file is copied only after its bytes are identified; byte-identical media
 *    is stored once (an in-run MD5 index plus an existing-file check);
 *  - an existing target file is reused only when its bytes match. Different
 *    bytes at the same path are reported as a conflict and never overwritten;
 *  - files created by this instance are tracked so a failed record's copies can
 *    be removed when its database transaction rolls back.
 */
final class MediaImporter
{
    /** @var array<string, string> md5 => stored path */
    private array $byHash = [];

    /** @var list<string> */
    private array $created = [];

    public function __construct(
        private readonly string $disk,
        private readonly string $directory = 'uploads/news',
    ) {}

    public function plan(ResolvedMedia $media): MediaCopyPlan
    {
        $target = $this->targetPath($media->manifestPath);

        if ($target === null) {
            return new MediaCopyPlan($media, null, null, MediaCopyPlan::CONFLICT, 'the manifest path is not a safe storage path');
        }

        $md5 = @md5_file($media->absolutePath);

        if ($md5 === false) {
            return new MediaCopyPlan($media, $target, null, MediaCopyPlan::CONFLICT, 'the source file could not be read');
        }

        if (isset($this->byHash[$md5])) {
            return new MediaCopyPlan($media, $this->byHash[$md5], $md5, MediaCopyPlan::REUSE);
        }

        if ($this->exists($target)) {
            if ($this->existingMd5($target) === $md5) {
                $this->byHash[$md5] = $target;

                return new MediaCopyPlan($media, $target, $md5, MediaCopyPlan::REUSE);
            }

            return new MediaCopyPlan(
                $media,
                $target,
                $md5,
                MediaCopyPlan::CONFLICT,
                "a different file already exists at {$target}",
            );
        }

        $this->byHash[$md5] = $target;

        return new MediaCopyPlan($media, $target, $md5, MediaCopyPlan::COPY);
    }

    /**
     * Perform a planned copy (or confirm a planned reuse).
     *
     * @return string|null The stored path, or null when the plan could not be
     *                     carried out.
     */
    public function copy(MediaCopyPlan $plan): ?string
    {
        if ($plan->reused()) {
            return $plan->targetPath;
        }

        if (! $plan->willCopy() || $plan->targetPath === null) {
            return null;
        }

        $stream = @fopen($plan->media->absolutePath, 'rb');

        if ($stream === false) {
            return null;
        }

        $stored = Storage::disk($this->disk)->put($plan->targetPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $stored) {
            return null;
        }

        $this->created[] = $plan->targetPath;

        if ($plan->sourceMd5 !== null) {
            $this->byHash[$plan->sourceMd5] = $plan->targetPath;
        }

        return $plan->targetPath;
    }

    public function createdCount(): int
    {
        return count($this->created);
    }

    /**
     * Paths written since `$offset` — the copies a single record is responsible
     * for, used to clean up when that record's transaction fails.
     *
     * @return list<string>
     */
    public function createdSince(int $offset): array
    {
        return array_values(array_slice($this->created, $offset));
    }

    /**
     * Delete copies made by this run.
     *
     * @param  list<string>  $paths
     * @return list<string> Paths that could not be removed (reported as orphans).
     */
    public function releaseCreated(array $paths): array
    {
        $storage = Storage::disk($this->disk);
        $leftovers = [];

        foreach ($paths as $path) {
            try {
                if (! $storage->exists($path)) {
                    continue;
                }

                if (! $storage->delete($path)) {
                    $leftovers[] = $path;
                }
            } catch (\Throwable) {
                $leftovers[] = $path;
            }
        }

        $this->created = array_values(array_diff($this->created, $paths));

        // A released file no longer exists, so its byte index must forget it;
        // otherwise a later record would "reuse" a path that was just deleted.
        foreach ($this->byHash as $hash => $path) {
            if (in_array($path, $paths, true)) {
                unset($this->byHash[$hash]);
            }
        }

        return $leftovers;
    }

    /**
     * `uploads/news/<sanitised manifest path>`, or null when a segment is
     * empty or attempts traversal.
     */
    private function targetPath(string $manifestPath): ?string
    {
        $segments = [];

        foreach (preg_split('#[\\\\/]#', $manifestPath) ?: [] as $segment) {
            $segment = trim($segment);

            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            $clean = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment), '.');

            if ($clean === '') {
                return null;
            }

            $segments[] = $clean;
        }

        if ($segments === []) {
            return null;
        }

        return $this->directory.'/'.implode('/', $segments);
    }

    private function exists(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function existingMd5(string $path): ?string
    {
        try {
            $contents = Storage::disk($this->disk)->get($path);
        } catch (\Throwable) {
            return null;
        }

        return md5($contents);
    }
}
