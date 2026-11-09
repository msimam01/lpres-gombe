<?php

namespace App\Migration\WordPress;

/**
 * A parsed WordPress export: site metadata, taxonomy definitions and every
 * item, with the aggregate queries the import report needs.
 */
final readonly class WordPressExport
{
    /**
     * @param  list<array<string, string>>  $authors
     * @param  list<array<string, string>>  $categories
     * @param  list<array<string, string>>  $tags
     * @param  list<array<string, string>>  $terms
     * @param  list<WordPressItem>  $items
     */
    public function __construct(
        public string $path,
        public int $bytes,
        public string $wxrVersion,
        public string $siteTitle,
        public string $siteUrl,
        public array $authors = [],
        public array $categories = [],
        public array $tags = [],
        public array $terms = [],
        public array $items = [],
    ) {}

    /**
     * @return list<WordPressItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Item count per `wp:post_type`, largest first.
     *
     * @return array<string, int>
     */
    public function countsByType(): array
    {
        return $this->countBy(fn (WordPressItem $item) => $item->type);
    }

    /**
     * Item count per `wp:status`.
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array
    {
        return $this->countBy(fn (WordPressItem $item) => $item->status);
    }

    /**
     * Status counts for one post type, e.g. ['publish' => 144, 'draft' => 3].
     *
     * @return array<string, int>
     */
    public function countsByStatusForType(string $type): array
    {
        $counts = [];

        foreach ($this->items as $item) {
            if ($item->type === $type) {
                $counts[$item->status] = ($counts[$item->status] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * @return list<WordPressItem>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->items,
            fn (WordPressItem $item) => $item->type === $type,
        ));
    }

    public function find(int $sourceId): ?WordPressItem
    {
        foreach ($this->items as $item) {
            if ($item->sourceId === $sourceId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_map(fn (WordPressItem $item) => $item->sourceId, $this->items);
    }

    /**
     * @param  callable(WordPressItem): string  $key
     * @return array<string, int>
     */
    private function countBy(callable $key): array
    {
        $counts = [];

        foreach ($this->items as $item) {
            $value = $key($item);
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
