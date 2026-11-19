<?php

/**
 * Temporary QA content generator.
 *
 *   php scripts/qa-fake-content.php create
 *   php scripts/qa-fake-content.php delete
 *
 * `create` adds 10 placeholder projects (one per LGA), 4 galleries with images,
 * standalone media images and 6 events. Every id and copied file path is
 * written to storage/app/qa-fake-content.json; `delete` removes exactly those
 * records and files again and never touches real content.
 */

use App\Models\Event;
use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\MediaItem;
use App\Models\Project;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$registryPath = storage_path('app/qa-fake-content.json');
$publicPath = storage_path('app/public');
$mode = $argv[1] ?? 'create';

/** Tracked ids/files for the delete mode. */
$registry = ['ids' => ['projects' => [], 'events' => [], 'galleries' => [], 'media' => []], 'files' => []];

if (is_file($registryPath)) {
    $registry = array_replace_recursive($registry, json_decode((string) file_get_contents($registryPath), true) ?: []);
}

/** Every image already on the public disk, used as placeholder photography. */
$sourceImages = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicPath.'/uploads/projects')) as $file) {
    if (! $file->isFile()) {
        continue;
    }
    if (! preg_match('/\.(jpe?g|png|webp)$/i', $file->getFilename())) {
        continue;
    }
    if ($file->getSize() < 8000) {
        continue;
    }
    $sourceImages[] = str_replace('\\', '/', substr($file->getPathname(), strlen($publicPath) + 1));
}
sort($sourceImages);

if ($sourceImages === []) {
    fwrite(STDERR, "No placeholder photos found under storage/app/public/uploads/projects.\n");
    exit(1);
}

/** Copy a placeholder photo into the given uploads sub-folder. */
$copyImage = function (string $folder, int $offset = 0) use (&$sourceImages, &$registry, $publicPath): string {
    static $cursor = 0;
    $source = $sourceImages[($cursor += 1 + $offset) % count($sourceImages)];
    $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'jpg';
    $relative = 'uploads/'.$folder.'/qa/'.Str::lower(Str::random(32)).'.'.$extension;

    if (! is_dir(dirname($publicPath.'/'.$relative))) {
        mkdir(dirname($publicPath.'/'.$relative), 0775, true);
    }

    copy($publicPath.'/'.$source, $publicPath.'/'.$relative);

    $registry['files'][] = $relative;

    return $relative;
};

$uniqueSlug = function (string $model, string $title): string {
    $base = Str::slug($title) ?: Str::lower(Str::random(10));
    $slug = $base;
    $attempt = 2;
    while ($model::query()->where('slug', $slug)->exists()) {
        $slug = $base.'-'.$attempt++;
    }

    return $slug;
};

if ($mode === 'delete') {
    $removed = ['projects' => 0, 'events' => 0, 'galleries' => 0, 'media' => 0];

    foreach ($registry['ids']['media'] as $id) {
        $removed['media'] += MediaItem::query()->whereKey($id)->delete();
    }
    foreach ($registry['ids']['galleries'] as $id) {
        GalleryImage::query()->where('gallery_id', $id)->delete();
        $removed['galleries'] += Gallery::query()->whereKey($id)->delete();
    }
    foreach ($registry['ids']['events'] as $id) {
        $removed['events'] += Event::query()->whereKey($id)->delete();
    }
    foreach ($registry['ids']['projects'] as $id) {
        $removed['projects'] += Project::query()->whereKey($id)->delete();
    }

    foreach ($registry['files'] as $relative) {
        $path = $publicPath.'/'.$relative;
        if (is_file($path)) {
            unlink($path);
        }
    }

    // Remove the now-empty qa folders; failing silently is fine.
    foreach (glob($publicPath.'/uploads/*/qa', GLOB_ONLYDIR) ?: [] as $dir) {
        @rmdir($dir);
    }

    if (is_file($registryPath)) {
        unlink($registryPath);
    }

    echo "Removed QA content: ".json_encode($removed)." and ".count($registry['files'])." files.\n";
    exit(0);
}

if ($mode !== 'create') {
    fwrite(STDERR, "Usage: php scripts/qa-fake-content.php [create|delete]\n");
    exit(1);
}

$categoryId = App\Models\ProjectCategory::query()->where('slug', 'projects')->value('id');

/* -------------------------------------------------------------------------
 | Projects — one per LGA, each with an exact public position and 3 photos.
 * ---------------------------------------------------------------------- */

$projectSpecs = [
    ['Gombe Dairy Collection Centre Upgrade', 'Gombe', 'Jekadafari', 10.2897000, 11.1673000, 'Off Bauchi Road, Jekadafari, Gombe'],
    ['Akko Poultry Feed Mill Support', 'Akko', 'Kumo', 10.0333000, 10.9667000, 'Kumo–Gombe Road, Kumo, Akko'],
    ['Balanga Small Ruminant Breeding Scheme', 'Balanga', 'Talasse', 9.9333000, 11.6833000, 'Talasse Veterinary Office, Balanga'],
    ['Billiri Goat Market Modernisation', 'Billiri', 'Billiri East', 9.8667000, 11.2333000, 'Billiri Central Market, Billiri'],
    ['Dukku Livestock Water Scheme', 'Dukku', 'Dukku Central', 10.8167000, 10.7667000, 'Dukku Cattle Market, Dukku'],
    ['Funakaye Beef Processing Hub', 'Funakaye', 'Bajoga', 10.4500000, 11.4500000, 'Bajoga Industrial Layout, Funakaye'],
    ['Kwami Poultry Farmer Training Centre', 'Kwami', 'Malam Sidi', 10.5000000, 11.2000000, 'Malam Sidi Demonstration Farm, Kwami'],
    ['Nafada Cattle Market Access Road', 'Nafada', 'Nafada West', 11.1000000, 11.3000000, 'Nafada–Bajoga Road, Nafada'],
    ['Shongom Livestock Market Shelter', 'Shongom', 'Boh', 9.7000000, 11.1500000, 'Boh Market Square, Shongom'],
    ['Yamaltu-Deba Milk Chilling Plant', 'Yamaltu/Deba', 'Deba', 10.1500000, 11.3500000, 'Deba Milk Collection Point, Yamaltu/Deba'],
];

$projects = [];
foreach ($projectSpecs as $index => [$title, $lga, $ward, $lat, $lng, $address]) {
    $project = Project::query()->create([
        'title' => $title,
        'slug' => $uniqueSlug(Project::class, $title),
        'featured_image_path' => $copyImage('projects', $index),
        'gallery_image_paths' => [
            $copyImage('projects', $index + 1),
            $copyImage('projects', $index + 2),
            $copyImage('projects', $index + 3),
        ],
        'description' => 'Placeholder record for interface checks. The '.strtolower($title).' supports livestock farmers in '.$lga.' Local Government Area with improved handling, storage and market access for the L-PRES value chains.',
        'project_category_id' => $categoryId,
        'status' => 'published',
        'address' => $address,
        'latitude' => $lat,
        'longitude' => $lng,
        'lga' => $lga,
        'ward' => $ward,
        'location_visibility' => 'exact',
    ]);

    $projects[$lga] = $project;
    $registry['ids']['projects'][] = $project->id;
}

/* -------------------------------------------------------------------------
 | Galleries — 3 standalone plus 1 associated with a project.
 * ---------------------------------------------------------------------- */

$gallerySpecs = [
    ['Field Visit to Akko Poultry Clusters', null, 8],
    ['Livestock Market Day in Dukku', null, 7],
    ['Women in Livestock: Gombe State', null, 6],
    ['Beef Processing Hub Commissioning', $projects['Funakaye'] ?? null, 6],
];

foreach ($gallerySpecs as [$title, $project, $imageCount]) {
    $gallery = Gallery::query()->create([
        'title' => $title,
        'slug' => $uniqueSlug(Gallery::class, $title),
        'description' => 'Placeholder gallery created for interface checks.',
        'status' => 'published',
        'associated_type' => $project ? 'project' : null,
        'associated_id' => $project?->id,
    ]);

    for ($i = 0; $i < $imageCount; $i++) {
        GalleryImage::query()->create([
            'gallery_id' => $gallery->id,
            'path' => $copyImage('media/galleries', $i),
            'sort_index' => $i,
        ]);
    }

    $registry['ids']['galleries'][] = $gallery->id;
}

/* -------------------------------------------------------------------------
 | Standalone media images.
 * ---------------------------------------------------------------------- */

for ($i = 1; $i <= 6; $i++) {
    $media = MediaItem::query()->create([
        'title' => 'Project field photograph '.$i,
        'media_type' => MediaItem::TYPE_IMAGE,
        'image_path' => $copyImage('media/images', $i),
        'caption' => 'Placeholder standalone image for interface checks.',
        'status' => 'published',
    ]);

    $registry['ids']['media'][] = $media->id;
}

/* -------------------------------------------------------------------------
 | Events — five upcoming plus one recent, each with an image and gallery.
 * ---------------------------------------------------------------------- */

$eventSpecs = [
    ['Gombe State Livestock Development Stakeholders Forum', '2026-12-02', '2026-12-03', 'Gombe International Conference Centre'],
    ['L-PRES Poultry Value Chain Field Day', '2026-12-10', null, 'Kumo, Akko LGA'],
    ['Beef Productivity Training for Cluster Farmers', '2027-01-14', '2027-01-16', 'Bajoga, Funakaye LGA'],
    ['Small Ruminant Health Screening Outreach', '2027-02-05', null, 'Kaltungo LGA'],
    ['Dairy Cooperative Launch', '2027-03-11', null, 'Deba, Yamaltu/Deba LGA'],
    ['Cluster Beneficiary Verification Exercise', '2026-11-05', '2026-11-06', 'Gombe LGA'],
];

foreach ($eventSpecs as $index => [$title, $startsAt, $endsAt, $location]) {
    $event = Event::query()->create([
        'title' => $title,
        'slug' => $uniqueSlug(Event::class, $title),
        'image_path' => $copyImage('events', $index),
        'gallery_image_paths' => [
            $copyImage('events', $index + 1),
            $copyImage('events', $index + 2),
        ],
        'description' => 'Placeholder event created for interface checks. The '.strtolower($title).' brings together L-PRES beneficiaries, service providers and State officials.',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'location' => $location,
        'status' => 'published',
    ]);

    $registry['ids']['events'][] = $event->id;
}

file_put_contents($registryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Created: ".count($registry['ids']['projects'])." projects, ".count($registry['ids']['events'])." events, "
    .count($registry['ids']['galleries'])." galleries, ".count($registry['ids']['media'])." media items, "
    .count($registry['files'])." image files.\n";
echo "Tracked in storage/app/qa-fake-content.json — remove with: php scripts/qa-fake-content.php delete\n";
