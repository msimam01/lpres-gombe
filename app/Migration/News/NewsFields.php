<?php

namespace App\Migration\News;

use App\Migration\HtmlToText;
use App\Migration\WordPress\WordPressItem;
use App\Models\News;
use Illuminate\Support\Str;

/**
 * WordPress -> Laravel field mapping for one news article.
 *
 * Deliberately narrow: it only produces values the existing `news` columns can
 * hold, plus the payload used to fingerprint the imported target.
 *
 *  - Title is entity-decoded (`&amp;` -> `&`).
 *  - Body is converted from HTML to the plain text the detail page renders.
 *  - The leading heading is dropped when it repeats the title (115 of the 132
 *    real articles open with the title as a heading; 6 of those have the body
 *    glued to it with no tag or space between, e.g. `...ProductionIn a
 *    collaborative effort`).
 *  - A body with no block structure at all is split into paragraphs on
 *    sentence boundaries: 90 of the 132 articles contain no `<p>` and would
 *    otherwise render as one 3 000-character paragraph (audit D16).
 *  - Excerpt follows the application's existing rule: derived from the title.
 *  - Publication date is the WordPress date part, never "today".
 *  - No author is invented: WordPress has no byline column to map to.
 */
final class NewsFields
{
    public function __construct(private readonly HtmlToText $html) {}

    public function title(WordPressItem $item): string
    {
        return trim(html_entity_decode($item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Plain-text article body in the shape `news/show` expects (paragraphs
     * separated by one blank line).
     */
    public function body(WordPressItem $item): string
    {
        $text = $this->html->convert($item->content);

        if ($text === '') {
            return '';
        }

        $text = $this->stripLeadingTitle($text, $this->title($item));

        return $this->splitIntoParagraphs($text);
    }

    /**
     * The WordPress slug, normalised but not truncated: preserving the original
     * permalink is preferred over regenerating one from the title.
     */
    public function slug(WordPressItem $item): string
    {
        return Str::slug($item->slug);
    }

    /**
     * Slug derived from the title, matching the admin module's rule (title
     * limited to 120 characters) for records WordPress left without one.
     */
    public function generatedSlug(WordPressItem $item): string
    {
        $title = $this->title($item);

        return $title === '' ? '' : Str::slug(Str::limit($title, 120, ''));
    }

    /**
     * Date part of `wp:post_date`; null when absent, so the importer refuses
     * the record instead of substituting the current date.
     */
    public function publishedDate(WordPressItem $item): ?string
    {
        return $item->publishedDate();
    }

    /**
     * The fields the importer writes, as stored on the Laravel record. Used to
     * fingerprint the target so administrator edits are detectable.
     *
     * @return array<string, mixed>
     */
    public function targetPayload(News $news): array
    {
        return [
            'title' => $news->title,
            'slug' => $news->slug,
            'body' => $news->body,
            'excerpt' => $news->excerpt,
            'published_at' => $news->published_at?->toDateString(),
            'status' => $news->status,
            'featured_image_path' => $news->featured_image_path,
            'gallery_image_paths' => array_values((array) ($news->gallery_image_paths ?? [])),
            'category_ids' => $news->categories()
                ->pluck('news_categories.id')
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * A paragraph is shorter than this only when WordPress already broke it up.
     */
    private const SINGLE_PARAGRAPH_LIMIT = 700;

    /**
     * Target length when splitting an unstructured body into paragraphs.
     */
    private const PARAGRAPH_TARGET = 350;

    /**
     * Tokens whose trailing full stop is not a sentence end.
     */
    private const ABBREVIATIONS = [
        'mr', 'mrs', 'ms', 'dr', 'prof', 'engr', 'hon', 'sen', 'rep', 'barr',
        'gen', 'col', 'capt', 'lt', 'sgt', 'chief', 'alh', 'alhaji', 'hajia',
        'st', 'no', 'vol', 'co', 'ltd', 'inc', 'plc', 'vs', 'etc', 'eg', 'ie',
        'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'sept', 'oct', 'nov', 'dec',
    ];

    /**
     * Remove a leading repetition of the title.
     *
     * Two shapes exist in the source: a heading that is exactly the title (95
     * articles) and a title glued to the first sentence (6 articles). Matching
     * is case-, quote- and whitespace-insensitive, and is only attempted for a
     * title long enough that it cannot be an ordinary opening word.
     */
    private function stripLeadingTitle(string $text, string $title): string
    {
        $headline = $this->matchKey($title);

        if ($headline === '' || mb_strlen($headline) < 12) {
            return $text;
        }

        $parts = preg_split('/\n{2,}/', $text, 2) ?: [];
        $first = $parts[0] ?? '';
        $remainder = $parts[1] ?? '';
        $opening = $this->matchKey($first);

        if ($opening === $headline) {
            return ltrim($remainder);
        }

        if (! str_starts_with($opening, $headline)) {
            return $text;
        }

        // The heading and the first sentence share one paragraph: consume the
        // title's words from the original string so the body keeps its own
        // capitalisation and punctuation.
        $words = preg_split('/\s+/u', $headline) ?: [];
        $pattern = '/^\s*'.implode('\\s*[\\s.,:;\\-–—|]\\s*', array_map(
            fn (string $word) => preg_quote($word, '/'),
            $words,
        )).'/iu';

        if (preg_match($pattern, $first, $matched) !== 1) {
            return $text;
        }

        $body = ltrim(substr($first, strlen($matched[0])), " \t\n.:;-–—|");

        if ($body === '') {
            return ltrim($remainder);
        }

        return $remainder === '' ? $body : $body."\n\n".ltrim($remainder);
    }

    /**
     * Comparable form of a string: entities decoded, quotes and dashes
     * unified, whitespace collapsed, case folded, trailing punctuation gone.
     */
    private function matchKey(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}", "\u{2013}", "\u{2014}", "\u{00A0}"],
            ["'", "'", '"', '"', '-', '-', ' '],
            $value,
        );
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value, " \t.:;-|"));
    }

    /**
     * Bodies WordPress never wrapped in block elements arrive as a single
     * paragraph. Split those on sentence boundaries so the detail page (which
     * splits on blank lines) stays readable. Structured bodies are untouched.
     */
    private function splitIntoParagraphs(string $text): string
    {
        $text = trim($text);

        if ($text === '' || preg_match('/\n{2,}/', $text) === 1) {
            return $text;
        }

        if (mb_strlen($text) <= self::SINGLE_PARAGRAPH_LIMIT) {
            return $text;
        }

        $paragraphs = [];
        $current = '';

        foreach ($this->sentences($text) as $sentence) {
            if ($current === '') {
                $current = $sentence;

                continue;
            }

            if (mb_strlen($current) >= self::PARAGRAPH_TARGET) {
                $paragraphs[] = $current;
                $current = $sentence;
            } else {
                $current .= ' '.$sentence;
            }
        }

        if ($current !== '') {
            $paragraphs[] = $current;
        }

        return implode("\n\n", $paragraphs);
    }

    /**
     * Sentence candidates, with splits after an abbreviation or an initial
     * (`Prof. U.B. Abubakar`) folded back into the sentence they belong to.
     *
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $candidates = preg_split(
            // \x{...} rather than \u{...}: PCRE2 rejects the latter.
            '/(?<=[.!?])\s+(?=[A-Z"\x{201C}\x{2018}\d])/u',
            $text,
        ) ?: [$text];

        $sentences = [];

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $last = count($sentences) - 1;

            if ($last >= 0 && $this->endsWithAbbreviation($sentences[$last])) {
                $sentences[$last] .= ' '.$candidate;

                continue;
            }

            $sentences[] = $candidate;
        }

        return $sentences;
    }

    /**
     * Whether the sentence's final token is an abbreviation or a single
     * initial, i.e. its full stop does not end the sentence.
     */
    private function endsWithAbbreviation(string $sentence): bool
    {
        if (preg_match('/\b([\p{L}.]{1,10})\.$/u', $sentence, $matches) !== 1) {
            return false;
        }

        $token = mb_strtolower($matches[1]);

        // Initials such as "U.B" (two letters and an inner full stop).
        if (preg_match('/^\p{L}(\.\p{L})+$/u', $matches[1]) === 1) {
            return true;
        }

        if (mb_strlen($token) === 1) {
            return true;
        }

        return in_array($token, self::ABBREVIATIONS, true);
    }
}
