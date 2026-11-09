<?php

use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressItem;
use App\Migration\WordPress\WordPressXmlParser;

function hashFixtureExport(): string
{
    return dirname(__DIR__, 3).'/tests/Fixtures/wordpress/sample-export.xml';
}

function hashItem(array $overrides = []): WordPressItem
{
    return new WordPressItem(
        sourceId: $overrides['sourceId'] ?? 5907,
        type: $overrides['type'] ?? 'post',
        status: $overrides['status'] ?? 'publish',
        title: $overrides['title'] ?? 'Gombe L-PRES Trains 67 Farmers',
        slug: $overrides['slug'] ?? 'trains-67-farmers',
        publishedAt: $overrides['publishedAt'] ?? '2025-04-25 09:52:56',
        modifiedAt: $overrides['modifiedAt'] ?? '2025-04-25 10:15:19',
        content: $overrides['content'] ?? '<p>Body</p>',
        terms: $overrides['terms'] ?? [
            ['domain' => 'category', 'nicename' => 'news', 'name' => 'News'],
            ['domain' => 'category', 'nicename' => 'latest-news', 'name' => 'Latest News'],
        ],
        meta: $overrides['meta'] ?? ['_thumbnail_id' => ['5909']],
        attachmentUrl: $overrides['attachmentUrl'] ?? '',
    );
}

test('it produces the same hash for the same source record', function () {
    $hasher = new SourceHash;
    $export = (new WordPressXmlParser)->parse(hashFixtureExport());
    $item = $export->find(5907);

    expect($hasher->forItem($item))->toBe($hasher->forItem($item));
});

test('it produces the same hash across separate parses of one export', function () {
    $hasher = new SourceHash;

    $first = (new WordPressXmlParser)->parse(hashFixtureExport())->find(5907);
    $second = (new WordPressXmlParser)->parse(hashFixtureExport())->find(5907);

    expect($hasher->forItem($first))->toBe($hasher->forItem($second));
});

test('it returns a sha256 hex digest', function () {
    expect((new SourceHash)->forItem(hashItem()))
        ->toMatch('/^[0-9a-f]{64}$/');
});

test('the hash changes when imported content changes', function () {
    $hasher = new SourceHash;

    $original = $hasher->forItem(hashItem());
    $edited = $hasher->forItem(hashItem(['content' => '<p>Body, edited</p>']));
    $retitled = $hasher->forItem(hashItem(['title' => 'A different title']));
    $republished = $hasher->forItem(hashItem(['status' => 'draft']));

    expect($edited)->not->toBe($original)
        ->and($retitled)->not->toBe($original)
        ->and($republished)->not->toBe($original);
});

test('the hash ignores taxonomy term order', function () {
    $hasher = new SourceHash;

    $ordered = $hasher->forItem(hashItem([
        'terms' => [
            ['domain' => 'category', 'nicename' => 'news', 'name' => 'News'],
            ['domain' => 'category', 'nicename' => 'latest-news', 'name' => 'Latest News'],
        ],
    ]));

    $reversed = $hasher->forItem(hashItem([
        'terms' => [
            ['domain' => 'category', 'nicename' => 'latest-news', 'name' => 'Latest News'],
            ['domain' => 'category', 'nicename' => 'news', 'name' => 'News'],
        ],
    ]));

    expect($reversed)->toBe($ordered);
});

test('different source records hash differently', function () {
    $hasher = new SourceHash;

    expect($hasher->forItem(hashItem(['sourceId' => 5907])))
        ->not->toBe($hasher->forItem(hashItem(['sourceId' => 5908])));
});

test('it hashes arbitrary payloads deterministically', function () {
    $hasher = new SourceHash;

    expect($hasher->forPayload(['b' => 2, 'a' => 1]))
        ->toBe($hasher->forPayload(['a' => 1, 'b' => 2]));
});
