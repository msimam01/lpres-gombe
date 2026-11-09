<?php

namespace App\Migration;

/**
 * The audited content inventory (`02-content-inventory.csv`).
 *
 * This file is the migration's eligibility register: it holds the audit's
 * classification for every non-attachment WordPress record
 * (`news`, `news_draft`, `value_chain_description`, `junk`, `demo_content`, …)
 * and its scope decision (`yes`, `no`, `review`, `absorb`).
 *
 * The importer selects news records from here rather than guessing from titles
 * or slugs, so exactly the audited 132 genuine articles are eligible and the
 * 12 published-but-not-news posts are excluded by decision, not by pattern.
 *
 * Reading is side-effect free.
 */
final class ContentInventory
{
    public const REQUIRED_COLUMNS = [
        'wp_id',
        'post_type',
        'status',
        'classification',
        'in_migration_scope',
    ];

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function __construct(
        public readonly string $path,
        public readonly array $rows,
    ) {}

    /**
     * @throws MigrationSourceException
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw MigrationSourceException::notFound($path, 'Content inventory');
        }

        if (! is_readable($path)) {
            throw MigrationSourceException::unreadable($path, 'Content inventory');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw MigrationSourceException::unreadable($path, 'Content inventory');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw MigrationSourceException::unreadableHeaderFor('Content inventory', $path);
        }

        $columns = array_map(fn (mixed $column) => strtolower(trim((string) $column)), $header);

        if (isset($columns[0]) && str_starts_with($columns[0], self::BOM)) {
            $columns[0] = substr($columns[0], strlen(self::BOM));
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $columns));

        if ($missing !== []) {
            fclose($handle);

            throw MigrationSourceException::missingColumnsFor('Content inventory', $path, $missing);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $mapped = [];

            foreach ($columns as $index => $column) {
                $mapped[$column] = trim((string) ($row[$index] ?? ''));
            }

            if ($mapped['wp_id'] === '') {
                continue;
            }

            $rows[] = $mapped;
        }

        fclose($handle);

        return new self($path, $rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Every inventory row, keyed by WordPress post id.
     *
     * @return array<int, array<string, string>>
     */
    public function byId(): array
    {
        $byId = [];

        foreach ($this->rows as $row) {
            $byId[(int) $row['wp_id']] = $row;
        }

        return $byId;
    }

    /**
     * Rows for ordinary WordPress posts, whatever their classification.
     *
     * @return list<array<string, string>>
     */
    public function posts(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn (array $row) => $row['post_type'] === 'post',
        ));
    }

    /**
     * The audited genuine news articles: published, classified `news` and in
     * migration scope. Exactly 132 records in the audited inventory.
     *
     * @return list<int>
     */
    public function eligibleNewsIds(): array
    {
        $ids = [];

        foreach ($this->posts() as $row) {
            if ($row['status'] === 'publish'
                && $row['classification'] === 'news'
                && $row['in_migration_scope'] === 'yes') {
                $ids[] = (int) $row['wp_id'];
            }
        }

        sort($ids);

        return $ids;
    }

    public function isEligibleNews(int $wpId): bool
    {
        $row = $this->byId()[$wpId] ?? null;

        return $row !== null
            && $row['post_type'] === 'post'
            && $row['status'] === 'publish'
            && $row['classification'] === 'news'
            && $row['in_migration_scope'] === 'yes';
    }

    /**
     * The audited genuine team member records: published, classified `team_member` and
     * in migration scope. Exactly 22 records in the audited inventory.
     *
     * @return list<int>
     */
    public function eligibleTeamIds(): array
    {
        $ids = [];

        foreach ($this->rows as $row) {
            if ($row['post_type'] === 'team'
                && $row['status'] === 'publish'
                && $row['classification'] === 'team_member'
                && $row['in_migration_scope'] === 'yes') {
                $ids[] = (int) $row['wp_id'];
            }
        }

        sort($ids);

        return $ids;
    }

    public function isEligibleTeam(int $wpId): bool
    {
        $row = $this->byId()[$wpId] ?? null;

        return $row !== null
            && $row['post_type'] === 'post'
            && $row['status'] === 'publish'
            && $row['classification'] === 'team_member'
            && $row['in_migration_scope'] === 'yes';
    }

    /**
     * The audited genuine project records: published, classified `project` and
     * in migration scope. Exactly 58 records in the audited inventory.
     *
     * @return list<int>
     */
    public function eligibleProjectIds(): array
    {
        $ids = [];

        foreach ($this->rows as $row) {
            if ($row['post_type'] === 'project'
                && $row['status'] === 'publish'
                && $row['classification'] === 'project'
                && $row['in_migration_scope'] === 'yes') {
                $ids[] = (int) $row['wp_id'];
            }
        }

        sort($ids);

        return $ids;
    }

    public function isEligibleProject(int $wpId): bool
    {
        $row = $this->byId()[$wpId] ?? null;

        return $row !== null
            && $row['post_type'] === 'project'
            && $row['status'] === 'publish'
            && $row['classification'] === 'project'
            && $row['in_migration_scope'] === 'yes';
    }
}
