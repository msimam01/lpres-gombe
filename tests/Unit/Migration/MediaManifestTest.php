<?php

use App\Migration\MediaManifest;
use App\Migration\MigrationSourceException;

function manifestFixture(string $name = 'media-manifest.csv'): string
{
    return dirname(__DIR__, 3).'/tests/Fixtures/wordpress/'.$name;
}

function mediaFixtureDirectory(): string
{
    return dirname(__DIR__, 3).'/tests/Fixtures/wordpress/media';
}

function tempManifest(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'lpres-csv-');
    file_put_contents($path, $contents);

    return $path;
}

test('it reads the manifest rows and columns', function () {
    $manifest = MediaManifest::fromFile(manifestFixture());

    expect($manifest->count())->toBe(2)
        ->and($manifest->columns)->toContain('url', 'relative_path', 'referenced_by', 'status', 'http_status', 'bytes')
        ->and($manifest->rows[0]['relative_path'])->toBe('2025/04/photo.jpg')
        ->and($manifest->rows[0]['http_status'])->toBe('200');
});

test('it counts unique urls', function () {
    expect(MediaManifest::fromFile(manifestFixture())->uniqueUrlCount())->toBe(2);
});

test('it tolerates a utf-8 bom on the header row', function () {
    $path = tempManifest(
        "\xEF\xBB\xBFurl,relative_path,referenced_by,status,http_status,bytes\n"
        ."https://example.test/a.jpg,2025/04/a.jpg,post:1,downloaded,200,10\n"
    );

    try {
        $manifest = MediaManifest::fromFile($path);

        expect($manifest->columns[0])->toBe('url')
            ->and($manifest->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

test('it skips blank lines', function () {
    $path = tempManifest(
        "url,relative_path,referenced_by,status,http_status,bytes\n"
        ."\n"
        ."https://example.test/a.jpg,2025/04/a.jpg,post:1,downloaded,200,10\n"
        ."\n"
    );

    try {
        expect(MediaManifest::fromFile($path)->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});

test('it reports a missing manifest file', function () {
    MediaManifest::fromFile(manifestFixture('absent.csv'));
})->throws(MigrationSourceException::class, 'Media manifest not found');

test('it rejects a manifest without the required columns', function () {
    $path = tempManifest("url,relative_path\nhttps://example.test/a.jpg,2025/04/a.jpg\n");

    try {
        MediaManifest::fromFile($path);
    } finally {
        @unlink($path);
    }
})->throws(MigrationSourceException::class, 'missing required column');

test('it finds no missing files when every row is on disk', function () {
    expect(MediaManifest::fromFile(manifestFixture())->missingFiles(mediaFixtureDirectory()))
        ->toBe([]);
});

test('it reports rows whose file is absent', function () {
    $missing = MediaManifest::fromFile(manifestFixture('media-manifest-missing.csv'))
        ->missingFiles(mediaFixtureDirectory());

    expect($missing)->toBe(['2025/04/missing.jpg']);
});

test('it flags rows that did not download successfully', function () {
    $path = tempManifest(
        "url,relative_path,referenced_by,status,http_status,bytes\n"
        ."https://example.test/a.jpg,2025/04/a.jpg,post:1,downloaded,200,10\n"
        ."https://example.test/b.jpg,2025/04/b.jpg,post:2,failed,404,0\n"
    );

    try {
        $unsuccessful = MediaManifest::fromFile($path)->unsuccessfulRows();

        expect($unsuccessful)->toHaveCount(1)
            ->and($unsuccessful[0])->toContain('failed');
    } finally {
        @unlink($path);
    }
});
