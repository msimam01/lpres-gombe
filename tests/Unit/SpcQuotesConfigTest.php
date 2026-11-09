<?php

/**
 * SPC Quotes are static configuration — there is no testimonials table — so the
 * migrated content is asserted directly against the config that the carousel reads.
 */
function spcQuotesConfig(): string
{
    return (string) file_get_contents(__DIR__.'/../../resources/js/config/site.ts');
}

/**
 * @return list<array{id: int, text: string, author: string, role: string}>
 */
function spcQuoteEntries(): array
{
    $ts = spcQuotesConfig();

    preg_match('/quotes: \[(.*?)\]\s*as \{/s', $ts, $block);
    preg_match_all(
        '/id: (\d+),\s*text: (["\'])(.*?)\2,\s*author: \'([^\']*)\',\s*role: \'([^\']*)\'/s',
        $block[1] ?? '',
        $matches,
        PREG_SET_ORDER,
    );

    return array_map(fn (array $match): array => [
        'id' => (int) $match[1],
        'text' => $match[3],
        'author' => $match[4],
        'role' => $match[5],
    ], $matches);
}

it('configures exactly the four audited SPC quotes', function () {
    $quotes = spcQuoteEntries();
    $ts = spcQuotesConfig();

    expect($quotes)->toHaveCount(4)
        ->and(array_column($quotes, 'id'))->toBe([1, 2, 3, 4])
        ->and($ts)->toContain("avatar: '/images/lpres/spc-prof-u-b-abubakar.png'")
        ->and($ts)->toContain("background: '/images/lpres/testimonial-bg-1.jpg'");

    foreach ($quotes as $quote) {
        expect($quote['author'])->toBe('Prof. U.B. Abubakar')
            ->and($quote['role'])->toBe('SPC')
            ->and($quote['text'])->not->toBeEmpty()
            ->and(str_ends_with($quote['text'], '"'))->toBeFalse();
    }

    // No duplicate wording: this is what would happen if WP 3115 were added.
    $normalised = array_map(
        fn (array $quote): string => trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($quote['text']))),
        $quotes,
    );

    expect(array_unique($normalised))->toHaveCount(4);
});

it('preserves the two previously published quotes verbatim', function () {
    $quotes = spcQuoteEntries();

    expect($quotes[0]['text'])->toBe(
        'The L-PRES Project: bridging gaps, fostering resilience, and driving progress in the livestock sector.'
    )->and($quotes[1]['text'])->toBe(
        'Investing in livestock productivity today ensures food security, economic stability, and environmental sustainability for tomorrow.'
    );
});

it('imports WP 114 with its original wording', function () {
    expect(spcQuoteEntries()[2]['text'])->toBe(
        "Resilient livestock systems aren't just about animals; they're about people, innovation, and sustainable development."
    );
});

it('imports WP 116 with only the trailing double quote removed', function () {
    $text = spcQuoteEntries()[3]['text'];

    expect($text)->toBe(
        'From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth.'
    )
        ->and(str_ends_with($text, 'growth.'))->toBeTrue()
        ->and(substr_count($text, '"'))->toBe(0)
        ->and(substr_count($text, ')'))->toBe(0);
});

it('records provenance and excludes WP 3115 and WP 3670', function () {
    $ts = spcQuotesConfig();
    $quotes = spcQuoteEntries();

    foreach (['WP 114', 'WP 116', 'WP 118', 'WP 3114', 'WP 3115', 'WP 3670'] as $reference) {
        expect($ts)->toContain($reference);
    }

    // WP 3115 repeats WP 118's wording, so only one copy of it may exist.
    $duplicateWording = array_filter(
        $quotes,
        fn (array $quote): bool => str_contains(mb_strtolower($quote['text']), 'the l-pres project: bridging gaps'),
    );

    expect($duplicateWording)->toHaveCount(1);
});
