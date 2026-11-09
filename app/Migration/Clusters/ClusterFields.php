<?php

namespace App\Migration\Clusters;

use App\Migration\WordPress\WordPressItem;
use RuntimeException;

/**
 * The nine audited value chain cluster pages, reduced to the values the
 * migration writes.
 *
 * The cluster set is deliberately fixed: the audit approved these exact nine
 * WordPress page ids and nothing else. Records are never selected by title or
 * by slug pattern, so an unrelated page cannot slip into the import.
 *
 * Each page holds one table (`S/NO | LGA | WARD | NUMBER OF BENEFICIARIES`)
 * and one chart image. Two pages (Broiler, Eggs) append a `TOTAL` row that is a
 * summary, not a beneficiary record, so it is counted and skipped rather than
 * imported. Eggs also repeats one row; that duplicate is listed in
 * {@see self::EXCLUSIONS} with the approved reason and is removed only when the
 * row still matches the audited signature.
 */
final class ClusterFields
{
    /**
     * WordPress page id => destination identity and chart attachment.
     *
     * `attachment_id` is the `wp-image-{id}` embedded in the page; it is kept
     * as provenance, not used to fetch the file (the audited movers were never
     * downloaded — the nine originals live in public/images/lpres/clusters).
     */
    public const CLUSTERS = [
        3846 => ['name' => 'Dairy', 'slug' => 'dairy', 'attachment_id' => 3847],
        3849 => ['name' => 'Beef', 'slug' => 'beef', 'attachment_id' => 3850],
        3852 => ['name' => 'Sheep and Goat', 'slug' => 'sheep-and-goat', 'attachment_id' => 3853],
        3855 => ['name' => 'Broiler', 'slug' => 'broiler', 'attachment_id' => 3856],
        3858 => ['name' => 'Eggs', 'slug' => 'eggs', 'attachment_id' => 3859],
        3861 => ['name' => 'Leather', 'slug' => 'leather', 'attachment_id' => 3862],
        3864 => ['name' => 'Feed', 'slug' => 'feed', 'attachment_id' => 3865],
        3867 => ['name' => 'Fodder', 'slug' => 'fodder', 'attachment_id' => 3868],
        3870 => ['name' => 'Pig', 'slug' => 'pig', 'attachment_id' => 3872],
    ];

    /**
     * Expected distinct rows and beneficiary totals, per the approved audit.
     */
    public const EXPECTED = [
        3846 => ['rows' => 11, 'beneficiaries' => 818],
        3849 => ['rows' => 11, 'beneficiaries' => 2263],
        3852 => ['rows' => 11, 'beneficiaries' => 6654],
        3855 => ['rows' => 11, 'beneficiaries' => 5221],
        3858 => ['rows' => 10, 'beneficiaries' => 566],
        3861 => ['rows' => 10, 'beneficiaries' => 49],
        3864 => ['rows' => 9, 'beneficiaries' => 184],
        3867 => ['rows' => 9, 'beneficiaries' => 40],
        3870 => ['rows' => 11, 'beneficiaries' => 190],
    ];

    /**
     * Audited exclusions: WordPress id => 1-based data-row position => reason.
     *
     * Eggs repeats `Kwami / Malam Sidi / 30` as its 11th row; the page's own
     * TOTAL row counts it twice. The 11th row is removed, and only while it is
     * still that exact record — if the source changed, the import fails instead
     * of silently dropping something else.
     */
    public const EXCLUSIONS = [
        3858 => [
            11 => [
                'lga' => 'Kwami',
                'ward' => 'Malam Sidi',
                'beneficiaries' => 30,
                'reason' => 'duplicate of data row 7 (Kwami / Malam Sidi / 30); the source TOTAL row counts it twice',
            ],
        ],
    ];

    /**
     * The Beef description established in the project (database/seeders/
     * BeefClusterSeeder.php), preserved verbatim. WordPress supplies no prose
     * for any cluster, so the other eight are left NULL rather than invented.
     */
    public const BEEF_DESCRIPTION = <<<'TEXT'
The Beef cluster is one of the commodity value chains of the Gombe State
L-PRES project. Beneficiary records below show the Local Government Areas,
wards and the number of beneficiaries reached under the cluster. The cluster
chart published on the original L-PRES website accompanies the table.
TEXT;

    public function name(int $wpId): ?string
    {
        return self::CLUSTERS[$wpId]['name'] ?? null;
    }

    public function slug(int $wpId): ?string
    {
        return self::CLUSTERS[$wpId]['slug'] ?? null;
    }

    /**
     * The description this cluster is allowed to carry. Only Beef has approved
     * project-authored text; everything else stays null.
     */
    public function description(int $wpId): ?string
    {
        return $wpId === 3849 ? self::BEEF_DESCRIPTION : null;
    }

    /**
     * Parse the beneficiary table of one page.
     *
     * Whitespace is normalised only: decoded entities (including the leading
     * `&nbsp;` on every ward cell) are trimmed and internal runs collapsed to a
     * single space. Wording and counts are never altered.
     *
     * @return array{rows: list<array{lga: string, ward: string, beneficiaries: int}>, summaryRows: int}
     *
     * @throws RuntimeException when the table is missing or a row is unusable.
     */
    public function table(WordPressItem $item): array
    {
        if (preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $item->content, $rowMatches) === false) {
            throw new RuntimeException('the page table could not be read');
        }

        $rows = [];
        $summaryRows = 0;

        foreach ($rowMatches[1] as $rowHtml) {
            if (preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cellMatches) === false) {
                continue;
            }

            $cells = array_map(fn (string $cell) => $this->cellText($cell), $cellMatches[1]);

            if ($cells === []) {
                continue;
            }

            if ($this->isHeaderRow($cells)) {
                continue;
            }

            if ($this->isSummaryRow($cells)) {
                $summaryRows++;

                continue;
            }

            if (count($cells) < 4) {
                throw new RuntimeException(sprintf(
                    'data row %d has %d cells; expected S/NO, LGA, WARD and NUMBER OF BENEFICIARIES',
                    count($rows) + 1,
                    count($cells),
                ));
            }

            $lga = $cells[1];
            $ward = $cells[2];
            $beneficiaries = $this->beneficiaryCount($cells[3], count($rows) + 1);

            if ($lga === '' || $ward === '') {
                throw new RuntimeException(sprintf(
                    'data row %d is missing its LGA or ward',
                    count($rows) + 1,
                ));
            }

            $rows[] = [
                'lga' => $lga,
                'ward' => $ward,
                'beneficiaries' => $beneficiaries,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('the page table has no beneficiary rows');
        }

        return ['rows' => $rows, 'summaryRows' => $summaryRows];
    }

    private function cellText(string $cell): string
    {
        // Entities are decoded after tag removal so `&nbsp;` becomes the
        // character it stands for and is caught by the whitespace pass.
        $text = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // \s covers ordinary whitespace; \p{Zs} covers the non-breaking and
        // other Unicode spaces used as padding in these tables.
        $text = preg_replace('/[\s\p{Zs}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $first = strtoupper($cells[0] ?? '');

        return str_contains($first, 'S/NO') || str_contains($first, 'S/N');
    }

    /**
     * @param  list<string>  $cells
     */
    private function isSummaryRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^TOTAL\b/i', $cell) === 1) {
                return true;
            }
        }

        return false;
    }

    private function beneficiaryCount(string $value, int $position): int
    {
        $normalised = str_replace([',', ' '], '', $value);

        if ($normalised === '' || ! ctype_digit($normalised)) {
            throw new RuntimeException(sprintf(
                'data row %d has an unreadable beneficiary count ("%s")',
                $position,
                $value,
            ));
        }

        return (int) $normalised;
    }
}
