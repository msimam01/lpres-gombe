<?php

namespace App\Migration\Media;

/**
 * One WordPress attachment resolved to the file that was actually downloaded.
 *
 * `manifestPath` is the manifest's own `relative_path` — never a path rebuilt
 * from a URL — and `absolutePath` is that path inside the configured media
 * directory. WordPress serves resized variants (`-1024x682`) and `-scaled`
 * originals, so resolution happens through the attachment record and the
 * manifest, never by matching the URL string the article happens to contain.
 */
final readonly class ResolvedMedia
{
    public function __construct(
        public int $attachmentId,
        public string $originalUrl,
        public string $manifestPath,
        public string $absolutePath,
        public string $title = '',
        public string $caption = '',
    ) {}

    public function exists(): bool
    {
        return is_file($this->absolutePath) && is_readable($this->absolutePath);
    }
}
