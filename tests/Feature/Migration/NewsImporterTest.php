<?php

use App\Migration\ContentInventory;
use App\Migration\HtmlToText;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\MediaManifest;
use App\Migration\MigrationPaths;
use App\Migration\News\NewsFields;
use App\Migration\News\NewsImporter;
use App\Migration\News\NewsImportReport;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressItem;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function useNewsFixtures(): void
{
    config([
        'lpres_migration.xml' => base_path('tests/Fixtures/wordpress/news/news-export.xml'),
        'lpres_migration.manifest' => base_path('tests/Fixtures/wordpress/news/news-manifest.csv'),
        'lpres_migration.media_directory' => base_path('tests/Fixtures/wordpress/news/media'),
        'lpres_migration.inventory' => base_path('tests/Fixtures/wordpress/news/news-inventory.csv'),
    ]);
}

function newsImporter(bool $forceAdminEdits = false): NewsImporter
{
    $paths = MigrationPaths::fromConfig();
    $export = (new WordPressXmlParser)->parse($paths->xml);

    return new NewsImporter(
        export: $export,
        inventory: ContentInventory::fromFile($paths->inventory),
        resolver: MediaResolver::fromExport($export, MediaManifest::fromFile($paths->manifest), $paths->mediaDirectory),
        mediaImporter: new MediaImporter($paths->mediaDisk, 'uploads/news'),
        fields: new NewsFields(new HtmlToText),
        hasher: new SourceHash,
        forceAdminEdits: $forceAdminEdits,
    );
}

function runNewsImport(bool $execute = true, bool $forceAdminEdits = false): NewsImportReport
{
    return newsImporter($forceAdminEdits)->run($execute);
}

/**
 * A copy of the news export with string replacements applied, so a test can
 * simulate a WordPress-side edit without touching the fixture on disk.
 *
 * @param  array<string, string>  $replacements
 */
function editedNewsExport(array $replacements): string
{
    $path = tempnam(sys_get_temp_dir(), 'lpres-news-');
    $xml = (string) file_get_contents(base_path('tests/Fixtures/wordpress/news/news-export.xml'));

    file_put_contents($path, str_replace(
        array_keys($replacements),
        array_values($replacements),
        $xml,
    ));

    return $path;
}

beforeEach(function () {
    useNewsFixtures();
    Storage::fake('public');
});

test('it imports exactly the audited eligible news articles', function () {
    $report = runNewsImport();

    // Eligible: 1001–1010 and 1013 (publish + news + in scope).
    expect($report->eligible)->toBe(11)
        ->and($report->created)->toBe(9)
        ->and($report->failed)->toBe(2)
        ->and(News::query()->count())->toBe(9)
        ->and(News::query()->whereIn('slug', ['draft-article', 'demo-post'])->exists())->toBeFalse();
});

test('it reports drafts and out-of-scope posts as excluded', function () {
    $report = runNewsImport();

    expect($report->excluded)->toMatchArray([
        'not published (draft)' => 1,
        'published but out of scope: demo_content' => 1,
    ]);
});

test('it creates exactly one News category and attaches every article', function () {
    runNewsImport();

    $categories = NewsCategory::query()->get();

    expect($categories)->toHaveCount(1)
        ->and($categories->first()->name)->toBe('News')
        ->and($categories->first()->slug)->toBe('news')
        ->and($categories->first()->wp_term_id)->toBe(67)
        ->and(News::query()->whereDoesntHave('categories')->count())->toBe(0);

    $article = News::query()->where('slug', 'first-article')->firstOrFail();

    expect($article->categories->pluck('slug')->all())->toBe(['news']);
});

test('it reuses an existing News category instead of duplicating it', function () {
    $existing = NewsCategory::create(['name' => 'News', 'slug' => 'news', 'wp_term_id' => 67]);

    $report = runNewsImport();

    expect(NewsCategory::query()->count())->toBe(1)
        ->and($report->categoryExists)->toBeTrue()
        ->and($report->categoryWillBeCreated)->toBeFalse()
        ->and(News::query()->where('slug', 'first-article')->firstOrFail()->categories->first()->id)
        ->toBe($existing->id);
});

test('it preserves the original WordPress category term ids as provenance', function () {
    runNewsImport();

    $first = WpImportMap::lookup('post', 1001);
    $second = WpImportMap::lookup('post', 1002);
    $news = NewsCategory::query()->where('slug', 'news')->firstOrFail();

    $terms = collect($first->source_metadata['wp_categories']);

    expect($terms->pluck('term_id')->all())->toBe([21, 67])
        ->and($terms->pluck('name')->all())->toBe(['Latest News', 'News'])
        // Uncategorised in WordPress, still one Laravel category.
        ->and(collect($second->source_metadata['wp_categories'])->pluck('term_id')->all())->toBe([1])
        ->and($first->source_metadata['laravel_news_category_ids'])->toBe([$news->id])
        ->and($second->source_metadata['laravel_news_category_ids'])->toBe([$news->id]);
});

test('it records the WordPress author as provenance, never as a byline', function () {
    runNewsImport();

    $map = WpImportMap::lookup('post', 1001);

    expect($map->source_metadata['wp_author'])->toBe('admin')
        ->and($map->source_metadata['wp_slug'])->toBe('first-article')
        ->and($map->source_metadata['wp_post_type'])->toBe('post');
});

test('it maps title, slug, publication date and status', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'first-article')->firstOrFail();

    expect($article->title)->toBe('First Article')
        ->and($article->published_at->toDateString())->toBe('2025-03-01')
        ->and($article->status)->toBe('published')
        ->and($article->excerpt)->toBe('First Article')
        ->and($article->created_at)->not->toBeNull();
});

test('it converts WordPress html into plain readable paragraphs', function () {
    runNewsImport();

    $body = News::query()->where('slug', 'first-article')->firstOrFail()->body;

    expect($body)->toStartWith('Para one')
        ->toContain('Para one & a half.')
        ->toContain('Para two here.')
        ->toContain('• Feed')
        ->toContain('• Fodder')
        ->toContain('the note')
        ->not->toContain('<p>')
        ->not->toContain('<h2>')
        ->not->toContain('tracker')
        ->not->toContain('display:none');
});

test('it drops a leading heading that repeats the title', function () {
    runNewsImport();

    $fourth = News::query()->where('slug', 'fourth-article')->firstOrFail();

    expect($fourth->body)->toBe('Body four.');
});

test('it drops a title glued to the first sentence', function () {
    runNewsImport();

    $fifth = News::query()->where('slug', 'fifth-article')->firstOrFail();

    expect($fifth->body)->toStartWith('This body begins immediately')
        ->not->toContain('Fifth ArticleThis');
});

test('it resolves the featured image through the attachment record and manifest', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'first-article')->firstOrFail();

    expect($article->featured_image_path)->toBe('uploads/news/2025/03/photo-a.jpg')
        ->and(Storage::disk('public')->exists('uploads/news/2025/03/photo-a.jpg'))->toBeTrue();
});

test('it keeps embedded images in author order', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'first-article')->firstOrFail();

    expect($article->galleryPaths())->toBe(['uploads/news/2025/03/photo-b.jpg'])
        ->and(Storage::disk('public')->exists('uploads/news/2025/03/photo-b.jpg'))->toBeTrue();
});

test('it does not repeat the featured image in the inline gallery', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'third-article')->firstOrFail();

    expect($article->featured_image_path)->toBe('uploads/news/2025/03/photo-a.jpg')
        ->and($article->galleryPaths())->toBe([]);
});

test('it stores byte-identical images once and resolves wordpress resize suffixes', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'second-article')->firstOrFail();

    // photo-b arrives as "-1024x682"; duplicate.jpg has the same bytes.
    expect($article->galleryPaths())->toBe(['uploads/news/2025/03/photo-b.jpg'])
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
});

test('it reports a missing embedded image and still imports the article', function () {
    $report = runNewsImport();

    $article = News::query()->where('slug', 'sixth-article')->firstOrFail();

    expect($article)->not->toBeNull()
        ->and($article->galleryPaths())->toBe([])
        ->and(implode(' ', $report->missingEmbedded))->toContain('missing.jpg');
});

test('it reports a featured image that cannot be resolved', function () {
    $path = editedNewsExport([
        '<wp:meta_value><![CDATA[2001]]></wp:meta_value>' => '<wp:meta_value><![CDATA[9999]]></wp:meta_value>',
    ]);

    try {
        config(['lpres_migration.xml' => $path]);

        $report = runNewsImport();
        $article = News::query()->where('slug', 'first-article')->firstOrFail();

        expect($article->featured_image_path)->toBeNull()
            ->and(implode(' ', $report->missingFeatured))->toContain('attachment #9999 is not in the WordPress export');
    } finally {
        @unlink($path);
    }
});

test('it maps every imported article to its WordPress post id', function () {
    runNewsImport();

    $map = WpImportMap::lookup('post', 1001);

    expect($map)->not->toBeNull()
        ->and($map->target_type)->toBe(News::class)
        ->and($map->target)->toBeInstanceOf(News::class)
        ->and($map->target->slug)->toBe('first-article')
        ->and($map->source_hash)->toHaveLength(64)
        ->and($map->imported_at)->not->toBeNull();
});

test('it maps every imported attachment to its stored laravel path', function () {
    runNewsImport();

    $photoA = WpImportMap::lookup('attachment', 2001);
    $duplicate = WpImportMap::lookup('attachment', 2004);

    expect($photoA->source_metadata['stored_path'])->toBe('uploads/news/2025/03/photo-a.jpg')
        ->and($photoA->source_metadata['source_url'])->toBe('https://example.test/wp-content/uploads/2025/03/photo-a.jpg')
        ->and($duplicate->source_metadata['stored_path'])->toBe('uploads/news/2025/03/photo-b.jpg')
        ->and(WpImportMap::lookup('attachment', 2003))->toBeNull();
});

test('it stores a target hash fingerprint of what it wrote', function () {
    runNewsImport();

    $map = WpImportMap::lookup('post', 1001);
    $article = News::query()->with('categories')->findOrFail($map->target_id);
    $expected = (new SourceHash)->forPayload((new NewsFields(new HtmlToText))->targetPayload($article));

    expect($map->target_hash)->toHaveLength(64)
        ->and($map->target_hash)->toBe($expected)
        ->and($map->target_hash)->not->toBe($map->source_hash);
});

test('it never writes anything during a dry run', function () {
    $report = runNewsImport(false);

    expect($report->dryRun)->toBeTrue()
        ->and($report->created)->toBe(9)
        ->and($report->mediaCopies)->toBe(2)
        ->and($report->categoryWillBeCreated)->toBeTrue()
        ->and($report->potentialDatabaseWrites())->toBeGreaterThan(0)
        ->and(News::query()->count())->toBe(0)
        ->and(NewsCategory::query()->count())->toBe(0)
        ->and(WpImportMap::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('a repeated import is idempotent', function () {
    runNewsImport();
    $files = Storage::disk('public')->allFiles();

    $report = runNewsImport();

    expect($report->created)->toBe(0)
        ->and($report->updated)->toBe(0)
        ->and($report->unchanged)->toBe(9)
        ->and($report->mappingRows)->toBe(0)
        ->and(News::query()->count())->toBe(9)
        ->and(NewsCategory::query()->count())->toBe(1)
        ->and(WpImportMap::query()->where('source_type', 'post')->count())->toBe(9)
        ->and(WpImportMap::query()->where('source_type', 'attachment')->count())->toBe(3)
        ->and(Storage::disk('public')->allFiles())->toEqual($files);
});

test('it updates an article when only the WordPress source changed', function () {
    runNewsImport();

    $path = editedNewsExport([
        '<![CDATA[<p>Body nine.</p>]]>' => '<![CDATA[<p>Body nine, revised.</p>]]>',
    ]);

    try {
        config(['lpres_migration.xml' => $path]);

        $report = runNewsImport();

        expect($report->sourceChanged)->toBe(1)
            ->and($report->updated)->toBe(1)
            ->and($report->adminModified)->toBe(0)
            ->and(News::query()->where('slug', 'ninth-article')->firstOrFail()->body)
            ->toBe('Body nine, revised.');
    } finally {
        @unlink($path);
    }
});

test('it protects an administrator-edited article from an automatic update', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'ninth-article')->firstOrFail();
    $article->update(['title' => 'Edited by an administrator']);

    $path = editedNewsExport([
        '<![CDATA[<p>Body nine.</p>]]>' => '<![CDATA[<p>Body nine, revised.</p>]]>',
    ]);

    try {
        config(['lpres_migration.xml' => $path]);

        $report = runNewsImport();

        expect($report->adminModified)->toBe(1)
            ->and($report->updated)->toBe(0)
            ->and(implode(' ', $report->adminModifiedDetails))->toContain('the article was edited')
            ->and($article->fresh()->title)->toBe('Edited by an administrator')
            ->and($article->fresh()->body)->toBe('Body nine.');
    } finally {
        @unlink($path);
    }
});

test('it overwrites an administrator-edited article only with the explicit override', function () {
    runNewsImport();

    $article = News::query()->where('slug', 'ninth-article')->firstOrFail();
    $article->update(['title' => 'Edited by an administrator']);

    $path = editedNewsExport([
        '<![CDATA[<p>Body nine.</p>]]>' => '<![CDATA[<p>Body nine, revised.</p>]]>',
    ]);

    try {
        config(['lpres_migration.xml' => $path]);

        $report = runNewsImport(true, true);

        expect($report->updated)->toBe(1)
            ->and($report->adminModified)->toBe(0)
            ->and($article->fresh()->title)->toBe('Ninth Article')
            ->and($article->fresh()->body)->toBe('Body nine, revised.');
    } finally {
        @unlink($path);
    }
});

test('a mapping without a target hash is treated conservatively', function () {
    runNewsImport();

    WpImportMap::lookup('post', 1001)->forceFill(['target_hash' => null])->save();

    $report = runNewsImport();

    expect($report->adminModified)->toBe(1)
        ->and(implode(' ', $report->adminModifiedDetails))->toContain('no target hash recorded')
        ->and(News::query()->where('slug', 'first-article')->firstOrFail()->title)->toBe('First Article');

    // The override is the only way past it.
    expect(runNewsImport(true, true)->updated)->toBe(1);
});

test('a mapping whose article is missing is reported as a conflict', function () {
    WpImportMap::create([
        'source_type' => 'post',
        'source_id' => 1009,
        'target_type' => News::class,
        'target_id' => 999_999,
        'source_hash' => null,
        'target_hash' => null,
        'imported_at' => now(),
    ]);

    $report = runNewsImport();

    expect($report->conflicts)->toBe(1)
        ->and(implode(' ', $report->conflictDetails))->toContain('its article is missing')
        ->and(News::query()->where('slug', 'ninth-article')->exists())->toBeFalse();
});

test('it resolves duplicate slugs safely and reports them', function () {
    News::create([
        'title' => 'Existing article',
        'slug' => 'first-article',
        'body' => 'Existing body',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $report = runNewsImport();

    expect(News::query()->where('slug', 'first-article-2')->exists())->toBeTrue()
        ->and(News::query()->where('slug', 'first-article-3')->exists())->toBeTrue()
        ->and($report->slugConflicts)->toHaveCount(2)
        ->and(implode(' ', $report->slugConflicts))->toContain('first-article-2');
});

test('a failing record leaves no article, mapping or orphaned copy behind', function () {
    DB::statement(
        "CREATE TRIGGER fail_first_article BEFORE INSERT ON news WHEN NEW.title = 'First Article' "
        ."BEGIN SELECT RAISE(ABORT, 'forced failure'); END"
    );

    $report = runNewsImport();

    $referenced = News::query()->get()
        ->flatMap(fn (News $article) => [$article->featured_image_path, ...$article->galleryPaths()])
        ->filter()
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($report->failed)->toBe(3)
        ->and(implode(' ', $report->failedDetails))->toContain('forced failure')
        ->and(News::query()->where('slug', 'first-article')->exists())->toBeFalse()
        ->and(WpImportMap::lookup('post', 1001))->toBeNull()
        ->and($report->orphanedFiles)->toBe([])
        ->and(Storage::disk('public')->allFiles())->toEqual($referenced);
});

test('it splits an unstructured body into readable paragraphs', function () {
    $fields = new NewsFields(new HtmlToText);

    $sentence = 'Gombe State is expanding livestock production across the three senatorial zones with new facilities. ';
    $item = new WordPressItem(
        sourceId: 1,
        type: 'post',
        status: 'publish',
        title: 'Long Article',
        slug: 'long-article',
        content: '<p>'.str_repeat(trim($sentence).' ', 12).'</p>',
    );

    $body = $fields->body($item);

    expect(mb_strlen($body))->toBeGreaterThan(1000)
        ->and(substr_count($body, "\n\n"))->toBeGreaterThan(1);
});

test('it does not break a paragraph after an abbreviation', function () {
    $fields = new NewsFields(new HtmlToText);

    $item = new WordPressItem(
        sourceId: 2,
        type: 'post',
        status: 'publish',
        title: 'Abbreviations',
        slug: 'abbreviations',
        content: '<p>'.str_repeat(
            'Prof. Usman Bello Abubakar said the programme will reach every ward in the state this year. ',
            10,
        ).'</p>',
    );

    expect($fields->body($item))->not->toContain("Prof.\n\n");
});

test('it refuses an article without a publication date instead of using today', function () {
    $report = runNewsImport();

    expect(implode(' ', $report->failedDetails))->toContain('no usable publication date')
        ->and(News::query()->where('slug', 'eighth-article')->exists())->toBeFalse();
});

test('it refuses an article whose body is empty after conversion', function () {
    $report = runNewsImport();

    expect(implode(' ', $report->failedDetails))->toContain('body is empty')
        ->and(News::query()->where('slug', 'seventh-article')->exists())->toBeFalse();
});
