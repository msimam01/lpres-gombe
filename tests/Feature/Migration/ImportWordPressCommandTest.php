<?php

use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressItem;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\Storage;

function useWordPressFixtures(string $manifest = 'media-manifest.csv'): void
{
    config([
        'lpres_migration.xml' => base_path('tests/Fixtures/wordpress/sample-export.xml'),
        'lpres_migration.manifest' => base_path('tests/Fixtures/wordpress/'.$manifest),
        'lpres_migration.media_directory' => base_path('tests/Fixtures/wordpress/media'),
        'lpres_migration.inventory' => base_path('tests/Fixtures/wordpress/content-inventory.csv'),
    ]);
}

function fixtureItem(int $sourceId): WordPressItem
{
    return (new WordPressXmlParser)
        ->parse(config('lpres_migration.xml'))
        ->find($sourceId);
}

beforeEach(function () {
    useWordPressFixtures();
    Storage::fake('public');
});

test('the dry run reports the export and media inventory', function () {
    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('WordPress export')
        ->expectsOutputToContain('Manifest rows')
        ->expectsOutputToContain('7 records')
        ->expectsOutputToContain('nothing was written')
        ->assertExitCode(0);
});

test('the dry run reports every post type in the export', function () {
    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('tribe_events')
        ->expectsOutputToContain('attachment')
        ->assertSuccessful();
});

test('the dry run writes no records and copies no media', function () {
    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(WpImportMap::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('it reports unmapped records as new', function () {
    // One expectation per output write: the whole summary is a single line.
    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('0 imported, 0 reserved, 7 new, 0 changed')
        ->assertExitCode(0);
});

test('it recognises records that were already imported', function () {
    WpImportMap::create([
        'source_type' => 'post',
        'source_id' => 5907,
        'target_type' => News::class,
        'target_id' => null,
        'source_hash' => (new SourceHash)->forItem(fixtureItem(5907)),
        'imported_at' => now(),
    ]);

    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('1 imported, 0 reserved, 6 new, 0 changed')
        ->assertExitCode(0);
});

test('it flags records whose source changed after import', function () {
    WpImportMap::create([
        'source_type' => 'post',
        'source_id' => 5907,
        'target_type' => News::class,
        'target_id' => null,
        'source_hash' => str_repeat('b', 64),
        'imported_at' => now(),
    ]);

    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('1 changed since import')
        ->assertExitCode(0);
});

test('it counts reserved mappings separately from imported ones', function () {
    WpImportMap::create([
        'source_type' => 'post',
        'source_id' => 5907,
        'target_type' => null,
        'target_id' => null,
        'source_hash' => null,
        'imported_at' => null,
    ]);

    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('0 imported, 1 reserved, 6 new, 0 changed')
        ->assertExitCode(0);
});

test('a missing xml export is reported as an actionable error', function () {
    config(['lpres_migration.xml' => base_path('tests/Fixtures/wordpress/absent.xml')]);

    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        // Path and remediation hint are printed on one line, so they are
        // asserted as one substring (see MigrationPathsTest for the hint).
        ->expectsOutputToContain('WordPress XML export not found:')
        ->assertExitCode(2);
});

test('an unparseable xml export fails without writing anything', function () {
    $path = tempnam(sys_get_temp_dir(), 'lpres-bad-');
    file_put_contents($path, '<rss><channel><item>');

    try {
        config(['lpres_migration.xml' => $path]);

        $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
            ->expectsOutputToContain('could not be parsed')
            ->assertExitCode(2);
    } finally {
        @unlink($path);
    }

    expect(WpImportMap::query()->count())->toBe(0);
});

test('a manifest row without its file fails the pre-flight check', function () {
    useWordPressFixtures('media-manifest-missing.csv');

    $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
        ->expectsOutputToContain('not on disk')
        ->expectsOutputToContain('2025/04/missing.jpg')
        ->assertExitCode(2);
});

test('a manifest with missing columns is rejected', function () {
    $path = tempnam(sys_get_temp_dir(), 'lpres-csv-');
    file_put_contents($path, "url,relative_path\nhttps://example.test/a.jpg,2025/04/a.jpg\n");

    try {
        config(['lpres_migration.manifest' => $path]);

        $this->artisan('lpres:import-wordpress', ['--dry-run' => true])
            ->expectsOutputToContain('missing required column')
            ->assertExitCode(2);
    } finally {
        @unlink($path);
    }
});

test('running without the dry run flag refuses and writes nothing', function () {
    $this->artisan('lpres:import-wordpress')
        ->expectsOutputToContain('Choose a content type')
        ->assertExitCode(1);

    expect(WpImportMap::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

// ---------------------------------------------------------------------
// News import mode
// ---------------------------------------------------------------------

test('a news dry run reports the plan and writes nothing', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--dry-run' => true])
        ->expectsOutputToContain('Eligible news records')
        ->expectsOutputToContain('Excluded (by the audited content inventory)')
        ->expectsOutputToContain('Potential database writes')
        ->expectsOutputToContain('Files to copy')
        ->expectsOutputToContain('nothing was written')
        ->assertExitCode(0);

    expect(News::query()->count())->toBe(0)
        ->and(NewsCategory::query()->count())->toBe(0)
        ->and(WpImportMap::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('the news dry run reports the eligible record count', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--dry-run' => true])
        ->expectsOutputToContain('Eligible news records')
        ->expectsOutputToContain('New articles')
        ->assertExitCode(0);
});

test('it imports news on confirm', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--confirm' => true])
        ->assertExitCode(0);

    $article = News::query()->where('slug', 'trains-67-farmers')->first();

    expect($article)->not->toBeNull()
        ->and($article->title)->toBe('Gombe L-PRES Trains 67 Farmers')
        ->and($article->status)->toBe('published')
        ->and($article->published_at->toDateString())->toBe('2025-04-25')
        ->and($article->featured_image_path)->toBe('uploads/news/2025/04/photo.jpg')
        ->and($article->categories->pluck('slug')->all())->toBe(['news'])
        ->and(Storage::disk('public')->exists('uploads/news/2025/04/photo.jpg'))->toBeTrue()
        ->and(WpImportMap::lookup('post', 5907)?->target_id)->toBe($article->id)
        // The project, page, team member and event in the export stay out.
        ->and(News::query()->count())->toBe(1);
});

test('a repeated news import is idempotent', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--confirm' => true])->assertExitCode(0);
    $files = Storage::disk('public')->allFiles();

    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--confirm' => true])
        ->expectsOutputToContain('Unchanged articles')
        ->assertExitCode(0);

    expect(News::query()->count())->toBe(1)
        ->and(NewsCategory::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toEqual($files);
});

test('it refuses an unsupported content type', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'invalid', '--dry-run' => true])
        ->expectsOutputToContain('is not supported')
        ->assertExitCode(2);

    expect(News::query()->count())->toBe(0);
});

test('it refuses --confirm without a content type', function () {
    $this->artisan('lpres:import-wordpress', ['--confirm' => true])
        ->expectsOutputToContain('needs a content type')
        ->assertExitCode(2);
});

test('it refuses --dry-run together with --confirm', function () {
    $this->artisan('lpres:import-wordpress', [
        '--type' => 'news',
        '--dry-run' => true,
        '--confirm' => true,
    ])
        ->expectsOutputToContain('cannot be combined')
        ->assertExitCode(2);
});

test('it will not overwrite an administrator edit unless forced', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--confirm' => true])->assertExitCode(0);

    News::query()->where('slug', 'trains-67-farmers')->firstOrFail()
        ->update(['title' => 'Edited by an administrator']);

    $this->artisan('lpres:import-wordpress', ['--type' => 'news', '--confirm' => true])
        ->expectsOutputToContain('Administrator edits not overwritten')
        ->assertExitCode(1);

    expect(News::query()->where('slug', 'trains-67-farmers')->firstOrFail()->title)
        ->toBe('Edited by an administrator');

    $this->artisan('lpres:import-wordpress', [
        '--type' => 'news',
        '--confirm' => true,
        '--force-overwrite-admin-edits' => true,
    ])->assertExitCode(0);

    expect(News::query()->where('slug', 'trains-67-farmers')->firstOrFail()->title)
        ->toBe('Gombe L-PRES Trains 67 Farmers');
});
