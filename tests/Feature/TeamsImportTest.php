<?php

use App\Migration\ContentInventory;
use App\Migration\HtmlToText;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\MediaManifest;
use App\Migration\SourceHash;
use App\Migration\Teams\TeamFields;
use App\Migration\Teams\TeamsImporter;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Team member import persistence
|--------------------------------------------------------------------------
|
| These tests use a small synthetic WordPress export so the persistence layer
| is exercised end to end without depending on the 18 MB audited export.
|
| They reproduce the original Phase 4 failure: `metadata()` read
| `$item->thumbnailId` as a property although `thumbnailId()` is a method, and
| Laravel converts that warning into an ErrorException, failing every record
| after its media had been planned.
|
*/

$fixtureDirectories = [];

afterEach(function () use (&$fixtureDirectories) {
    foreach ($fixtureDirectories as $directory) {
        File::deleteDirectory($directory);
    }

    $fixtureDirectories = [];
});

/**
 * Seed the three managed categories with the audited ids, exactly as the local
 * database holds them.
 */
function seedTeamCategories(): void
{
    $categories = [
        3 => ['professionals', 'Professionals'],
        4 => ['piu', 'PIUs'],
        5 => ['ass', 'Assistant'],
    ];

    foreach ($categories as $id => [$slug, $name]) {
        DB::table('team_categories')->insert([
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/**
 * Build a minimal WXR export, inventory and media manifest for three team
 * members: two PIUs and one Assistant.
 *
 * @return array{xml: string, inventory: string, manifest: string, media: string}
 */
function makeTeamsFixture(): array
{
    $base = storage_path('framework/testing/teams-'.uniqid());
    $media = $base.'/media';

    File::ensureDirectoryExists($media.'/2024/01', 0777, true);

    file_put_contents($media.'/2024/01/alpha.jpg', 'alpha-photo-bytes');
    file_put_contents($media.'/2024/01/beta.jpg', 'beta-photo-bytes');
    file_put_contents($media.'/2024/01/gamma.jpg', 'gamma-photo-bytes');

    $members = [
        ['id' => 101, 'title' => 'Alpha Member', 'slug' => 'alpha-member', 'cat' => 'piu', 'catName' => 'PIUs', 'designation' => 'Alpha Role', 'attachment' => 9001, 'file' => 'alpha.jpg'],
        ['id' => 102, 'title' => 'Beta Member', 'slug' => 'beta-member', 'cat' => 'piu', 'catName' => 'PIUs', 'designation' => 'Beta Analyst', 'attachment' => 9002, 'file' => 'beta.jpg'],
        ['id' => 103, 'title' => 'Gamma Member', 'slug' => 'gamma-member', 'cat' => 'ass', 'catName' => 'Assistant', 'designation' => 'Gamma Secretary', 'attachment' => 9003, 'file' => 'gamma.jpg'],
    ];

    $items = '';
    $inventory = "wp_id,post_type,status,title,slug,publication_date,modified_date,category_names,tags,featured_attachment_id,featured_image_relative_path,classification,in_migration_scope,embedded_media_count\n";
    $manifest = "url,relative_path,referenced_by,status,http_status,bytes\n";

    foreach ($members as $member) {
        $teamMeta = serialize(['team_designation' => $member['designation']]);

        $items .= <<<XML
  <item>
    <title>{$member['title']}</title>
    <link>https://example.test/team/{$member['slug']}/</link>
    <dc:creator><![CDATA[admin]]></dc:creator>
    <content:encoded><![CDATA[<p>Body for {$member['title']}</p>]]></content:encoded>
    <excerpt:encoded><![CDATA[]]></excerpt:encoded>
    <wp:post_id>{$member['id']}</wp:post_id>
    <wp:post_date>2024-01-02 03:04:05</wp:post_date>
    <wp:post_modified>2024-02-03 04:05:06</wp:post_modified>
    <wp:post_name>{$member['slug']}</wp:post_name>
    <wp:status>publish</wp:status>
    <wp:post_type>team</wp:post_type>
    <category domain="team_cat" nicename="{$member['cat']}"><![CDATA[{$member['catName']}]]></category>
    <wp:postmeta><wp:meta_key>_thumbnail_id</wp:meta_key><wp:meta_value>{$member['attachment']}</wp:meta_value></wp:postmeta>
    <wp:postmeta><wp:meta_key>towngov_meta_team</wp:meta_key><wp:meta_value><![CDATA[{$teamMeta}]]></wp:meta_value></wp:postmeta>
  </item>

XML;

        $attachmentId = $member['attachment'];
        $url = "https://example.test/wp-content/uploads/2024/01/{$member['file']}";
        $relative = "2024/01/{$member['file']}";

        $items .= <<<XML
  <item>
    <title>{$member['file']}</title>
    <link>https://example.test/?attachment_id={$attachmentId}</link>
    <content:encoded><![CDATA[]]></content:encoded>
    <excerpt:encoded><![CDATA[]]></excerpt:encoded>
    <wp:post_id>{$attachmentId}</wp:post_id>
    <wp:post_date>2024-01-02 03:04:05</wp:post_date>
    <wp:post_modified>2024-01-02 03:04:05</wp:post_modified>
    <wp:post_name>{$member['file']}</wp:post_name>
    <wp:status>inherit</wp:status>
    <wp:post_type>attachment</wp:post_type>
    <wp:attachment_url>{$url}</wp:attachment_url>
    <wp:post_parent>{$member['id']}</wp:post_parent>
  </item>

XML;

        $inventory .= "{$member['id']},team,publish,\"{$member['title']}\",{$member['slug']},2024-01-02 03:04:05,2024-02-03 04:05:06,team_cat:{$member['catName']} [{$member['cat']}],,{$attachmentId},{$relative},team_member,yes,0\n";
        $manifest .= "{$url},{$relative},{$member['id']},downloaded,200,17\n";
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Fixture export</title>
  <link>https://example.test</link>
  <wp:wxr_version>1.2</wp:wxr_version>
{$items}</channel>
</rss>
XML;

    $paths = [
        'xml' => $base.'/export.xml',
        'inventory' => $base.'/inventory.csv',
        'manifest' => $base.'/manifest.csv',
        'media' => $media,
    ];

    file_put_contents($paths['xml'], $xml);
    file_put_contents($paths['inventory'], $inventory);
    file_put_contents($paths['manifest'], $manifest);

    return $paths;
}

/**
 * Build the importer against the fixture and a fake public disk.
 */
function teamsImporterFor(array $fixture, bool $forceAdminEdits = false): TeamsImporter
{
    $export = (new WordPressXmlParser)->parse($fixture['xml']);
    $manifest = MediaManifest::fromFile($fixture['manifest']);

    return new TeamsImporter(
        export: $export,
        inventory: ContentInventory::fromFile($fixture['inventory']),
        resolver: MediaResolver::fromExport($export, $manifest, $fixture['media']),
        mediaImporter: new MediaImporter('public', 'uploads/team-members'),
        fields: new TeamFields(new HtmlToText),
        hasher: new SourceHash,
        forceAdminEdits: $forceAdminEdits,
    );
}

beforeEach(function () {
    Storage::fake('public');
    seedTeamCategories();
});

it('persists PIU and Assistant members with categories, photographs and designations', function () {
    $fixture = makeTeamsFixture();

    $report = teamsImporterFor($fixture)->run(true);

    expect($report->failed)->toBe(0)
        ->and($report->failedDetails)->toBe([])
        ->and($report->created)->toBe(3)
        ->and($report->categoryAssignments)->toBe(3)
        ->and($report->missingFeatured)->toBe([]);

    // The public page filters on the boolean flag, so the imported rows must be
    // published there too (a label written into the flag matches nothing).
    expect(TeamMember::published()->count())->toBe(3)
        ->and(DB::table('team_members')->where('status', 1)->count())->toBe(3);

    $alpha = TeamMember::query()->where('slug', 'alpha-member')->firstOrFail();
    $beta = TeamMember::query()->where('slug', 'beta-member')->firstOrFail();
    $gamma = TeamMember::query()->where('slug', 'gamma-member')->firstOrFail();

    // 4 = PIUs, 5 = Assistant (audited ids).
    expect($alpha->team_category_id)->toBe(4)
        ->and($beta->team_category_id)->toBe(4)
        ->and($gamma->team_category_id)->toBe(5)
        ->and($alpha->name)->toBe('Alpha Member')
        ->and($alpha->designation)->toBe('Alpha Role')
        ->and($gamma->designation)->toBe('Gamma Secretary')
        ->and($alpha->photograph_path)->toBe('uploads/team-members/2024/01/alpha.jpg')
        ->and($gamma->photograph_path)->toBe('uploads/team-members/2024/01/gamma.jpg');

    foreach (['2024/01/alpha.jpg', '2024/01/beta.jpg', '2024/01/gamma.jpg'] as $path) {
        expect(Storage::disk('public')->exists('uploads/team-members/'.$path))->toBeTrue();
    }

    // Provenance: one team mapping per member, one attachment mapping per photo.
    expect(WpImportMap::query()->where('source_type', 'team')->count())->toBe(3)
        ->and(WpImportMap::query()->where('source_type', 'attachment')->count())->toBe(3);

    $mapping = WpImportMap::lookup('team', 101);

    expect($mapping)->not->toBeNull()
        ->and($mapping->source_hash)->not->toBeNull()
        ->and($mapping->target_hash)->not->toBeNull()
        ->and($mapping->target_id)->toBe($alpha->id)
        ->and($mapping->sourceMeta('featured_attachment_id'))->toBe(9001)
        ->and($mapping->sourceMeta('laravel_team_category_id'))->toBe([4]);

    $attachment = WpImportMap::lookup('attachment', 9001);

    expect($attachment)->not->toBeNull()
        ->and($attachment->sourceMeta('stored_path'))->toBe('uploads/team-members/2024/01/alpha.jpg');
});

it('does not create duplicate team members when re-imported', function () {
    $fixture = makeTeamsFixture();

    teamsImporterFor($fixture)->run(true);
    $second = teamsImporterFor($fixture)->run(true);

    expect($second->failed)->toBe(0)
        ->and($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(3)
        ->and($second->existingMappings)->toBe(3)
        // A record's own slug is never reported as a collision on re-run.
        ->and($second->slugConflicts)->toBe([])
        ->and($second->conflicts)->toBe(0);

    expect(TeamMember::query()->count())->toBe(3)
        ->and(WpImportMap::query()->where('source_type', 'team')->count())->toBe(3)
        ->and(WpImportMap::query()->where('source_type', 'attachment')->count())->toBe(3);
});

it('does not overwrite a team member edited by an administrator', function () {
    $fixture = makeTeamsFixture();

    teamsImporterFor($fixture)->run(true);

    TeamMember::query()->where('slug', 'alpha-member')->update(['name' => 'Renamed By Admin']);

    $second = teamsImporterFor($fixture)->run(true);

    expect($second->failed)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(2)
        ->and($second->adminModified)->toBe(1)
        ->and($second->adminModifiedDetails)->not->toBe([]);

    expect(TeamMember::query()->where('slug', 'alpha-member')->value('name'))
        ->toBe('Renamed By Admin');
});

it('keeps the imported photograph when an administrator edits without uploading a new one', function () {
    $fixture = makeTeamsFixture();

    teamsImporterFor($fixture)->run(true);

    $member = TeamMember::query()->where('slug', 'alpha-member')->firstOrFail();
    $originalPhoto = $member->photograph_path;

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/team/'.$member->id.'/edit')
        ->assertOk();

    // The form omits the file input when nothing is picked, exactly like the
    // real edit screen; the stored photograph must survive the update.
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/team/'.$member->id, [
            'name' => 'Alpha Member Updated',
            'designation' => 'Updated Role',
            'team_category_id' => '4',
        ])
        ->assertRedirect(route('admin.team.index'));

    $member->refresh();

    expect($member->name)->toBe('Alpha Member Updated')
        ->and($member->designation)->toBe('Updated Role')
        ->and($member->photograph_path)->toBe($originalPhoto)
        ->and($member->status)->toBeTrue();

    expect(Storage::disk('public')->exists($originalPhoto))->toBeTrue();
});

it('reports a persistence failure with its actual cause', function () {
    $fixture = makeTeamsFixture();

    TeamMember::creating(function () {
        throw new RuntimeException('simulated persistence failure');
    });

    $report = teamsImporterFor($fixture)->run(true);

    expect($report->created)->toBe(0)
        ->and($report->failed)->toBe(3)
        ->and($report->failedDetails)->toHaveCount(3)
        ->and($report->failedDetails[0])->toContain('simulated persistence failure');

    // A failed record leaves no half-written member or mapping behind.
    expect(TeamMember::query()->count())->toBe(0)
        ->and(WpImportMap::query()->where('source_type', 'team')->count())->toBe(0)
        ->and(WpImportMap::query()->where('source_type', 'attachment')->count())->toBe(0);
});
