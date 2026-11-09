<?php

use App\Migration\WordPress\WordPressExportException;
use App\Migration\WordPress\WordPressXmlParser;

/**
 * The parser is deliberately framework-free, so these tests run without
 * booting the application or touching a database.
 */
function wxrFixture(string $name = 'sample-export.xml'): string
{
    return dirname(__DIR__, 3).'/tests/Fixtures/wordpress/'.$name;
}

function wxrTempFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'lpres-wxr-');
    file_put_contents($path, $contents);

    return $path;
}

test('it parses the export metadata and every item', function () {
    $export = (new WordPressXmlParser)->parse(wxrFixture());

    expect($export->count())->toBe(7)
        ->and($export->wxrVersion)->toBe('1.2')
        ->and($export->siteTitle)->toBe('Gombe State L-PRES')
        ->and($export->siteUrl)->toBe('https://example.test')
        ->and($export->bytes)->toBeGreaterThan(0)
        ->and($export->authors)->toHaveCount(1)
        ->and($export->categories)->toHaveCount(2)
        ->and($export->tags)->toHaveCount(1)
        ->and($export->terms)->toHaveCount(1);
});

test('it counts records by post type', function () {
    $counts = (new WordPressXmlParser)->parse(wxrFixture())->countsByType();

    expect($counts)->toMatchArray([
        'post' => 2,
        'project' => 1,
        'page' => 1,
        'team' => 1,
        'attachment' => 1,
        'tribe_events' => 1,
    ]);
});

test('it counts records by status and per type', function () {
    $export = (new WordPressXmlParser)->parse(wxrFixture());

    expect($export->countsByStatus())->toMatchArray([
        'publish' => 5,
        'draft' => 1,
        'inherit' => 1,
    ])->and($export->countsByStatusForType('post'))->toMatchArray([
        'publish' => 1,
        'draft' => 1,
    ]);
});

test('it extracts numeric WordPress source ids', function () {
    $export = (new WordPressXmlParser)->parse(wxrFixture());

    expect($export->ids())->toBe([5907, 5908, 5535, 49, 22, 77, 5909])
        ->and($export->find(5907)?->sourceId)->toBe(5907)
        ->and($export->find(999999))->toBeNull();
});

test('it extracts post type, status, slug and dates', function () {
    $item = (new WordPressXmlParser)->parse(wxrFixture())->find(5907);

    expect($item->type)->toBe('post')
        ->and($item->status)->toBe('publish')
        ->and($item->isPublished())->toBeTrue()
        ->and($item->slug)->toBe('trains-67-farmers')
        ->and($item->publishedDate())->toBe('2025-04-25')
        // The fixture has no <wp:post_modified>; an absent value is empty,
        // never a fabricated date.
        ->and($item->modifiedAt)->toBe('');
});

test('it extracts categories, terms, featured image and attachment urls', function () {
    $export = (new WordPressXmlParser)->parse(wxrFixture());

    $post = $export->find(5907);
    expect($post->categories())->toBe(['Latest News', 'News'])
        ->and($post->tags())->toBe([])
        ->and($post->thumbnailId())->toBe('5909')
        ->and($post->metaValue('_elementor_edit_mode'))->toBe('builder');

    $project = $export->find(5535);
    expect($project->termNames('project_cat'))->toBe(['Water Facility'])
        ->and($project->thumbnailId())->toBeNull();

    $attachment = $export->find(5909);
    expect($attachment->type)->toBe('attachment')
        ->and($attachment->status)->toBe('inherit')
        ->and($attachment->attachmentUrl)->toBe('https://example.test/wp-content/uploads/2025/04/photo.jpg')
        ->and($attachment->excerpt)->toBe('Farmers at the training')
        ->and($attachment->parentId)->toBe(5907);
});

test('it reads the older WXR 1.1 namespace', function () {
    $xml = file_get_contents(wxrFixture());
    $legacy = str_replace('http://wordpress.org/export/1.2/', 'http://wordpress.org/export/1.1/', $xml);
    $legacy = str_replace('<wp:wxr_version>1.2</wp:wxr_version>', '<wp:wxr_version>1.1</wp:wxr_version>', $legacy);
    $path = wxrTempFile($legacy);

    try {
        $export = (new WordPressXmlParser)->parse($path);

        expect($export->wxrVersion)->toBe('1.1')
            ->and($export->count())->toBe(7)
            ->and($export->find(5907)?->title)->toBe('Gombe L-PRES Trains 67 Farmers')
            ->and($export->find(5909)?->attachmentUrl)->toBe('https://example.test/wp-content/uploads/2025/04/photo.jpg');
    } finally {
        @unlink($path);
    }
});

test('it fails clearly when the export is missing', function () {
    (new WordPressXmlParser)->parse(wxrFixture('does-not-exist.xml'));
})->throws(WordPressExportException::class, 'not found');

test('it fails clearly when the export is empty', function () {
    $path = wxrTempFile('');

    try {
        (new WordPressXmlParser)->parse($path);
    } finally {
        @unlink($path);
    }
})->throws(WordPressExportException::class, 'empty');

test('it rejects well-formed XML that is not a WordPress export', function () {
    $path = wxrTempFile('<?xml version="1.0"?><rss version="2.0"><channel><title>Not WXR</title></channel></rss>');

    try {
        (new WordPressXmlParser)->parse($path);
    } finally {
        @unlink($path);
    }
})->throws(WordPressExportException::class, 'Not a WordPress eXtended RSS export');

test('it reports malformed xml instead of returning partial data', function () {
    $path = wxrTempFile('<rss><channel><item><wp:post_id>1</wp:post_id></channel></rss>');

    try {
        (new WordPressXmlParser)->parse($path);
    } finally {
        @unlink($path);
    }
})->throws(WordPressExportException::class, 'could not be parsed');

test('it rejects an item without a numeric post id', function () {
    $path = wxrTempFile(
        '<?xml version="1.0"?>'
        .'<rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/"><channel>'
        .'<wp:wxr_version>1.2</wp:wxr_version>'
        .'<item><title>Broken</title><wp:post_id>abc</wp:post_id><wp:post_type>post</wp:post_type></item>'
        .'</channel></rss>'
    );

    try {
        (new WordPressXmlParser)->parse($path);
    } finally {
        @unlink($path);
    }
})->throws(WordPressExportException::class, 'not a numeric WordPress ID');
