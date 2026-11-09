<?php

use App\Migration\MigrationPaths;

function validMigrationConfig(): array
{
    return [
        'lpres_migration.xml' => base_path('tests/Fixtures/wordpress/sample-export.xml'),
        'lpres_migration.manifest' => base_path('tests/Fixtures/wordpress/media-manifest.csv'),
        'lpres_migration.media_directory' => base_path('tests/Fixtures/wordpress/media'),
    ];
}

test('configured fixture paths produce no problems', function () {
    config(validMigrationConfig());

    $paths = MigrationPaths::fromConfig();

    expect($paths->isValid())->toBeTrue()
        ->and($paths->problems())->toBe([])
        ->and($paths->mediaDisk)->toBe('public');
});

test('a missing xml export names the environment variable to fix', function () {
    config(validMigrationConfig());
    config(['lpres_migration.xml' => base_path('tests/Fixtures/wordpress/absent.xml')]);

    $problems = MigrationPaths::fromConfig()->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('WordPress XML export not found')
        ->and($problems[0])->toContain('LPRES_MIGRATION_XML');
});

test('a missing media directory is reported separately', function () {
    config(validMigrationConfig());
    config(['lpres_migration.media_directory' => base_path('tests/Fixtures/wordpress/absent-media')]);

    $problems = MigrationPaths::fromConfig()->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('Media directory not found')
        ->and($problems[0])->toContain('LPRES_MIGRATION_MEDIA_DIR');
});

test('an empty path is reported as not configured rather than silently defaulted', function () {
    config(validMigrationConfig());
    config(['lpres_migration.manifest' => '']);

    $problems = MigrationPaths::fromConfig()->problems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('not configured');
});

test('empty files are reported as empty rather than valid', function () {
    $path = tempnam(sys_get_temp_dir(), 'lpres-empty-');

    try {
        config(validMigrationConfig());
        config(['lpres_migration.xml' => $path]);

        $problems = MigrationPaths::fromConfig()->problems();

        expect($problems)->toHaveCount(1)
            ->and($problems[0])->toContain('empty (0 bytes)');
    } finally {
        @unlink($path);
    }
});

test('relative paths resolve against the application base path', function () {
    config([
        'lpres_migration.xml' => 'tests/Fixtures/wordpress/sample-export.xml',
        'lpres_migration.manifest' => 'tests/Fixtures/wordpress/media-manifest.csv',
        'lpres_migration.media_directory' => 'tests/Fixtures/wordpress/media',
    ]);

    $paths = MigrationPaths::fromConfig();

    expect($paths->xml)->toBe(base_path('tests/Fixtures/wordpress/sample-export.xml'))
        ->and($paths->isValid())->toBeTrue();
});

test('all problems are collected together', function () {
    config([
        'lpres_migration.xml' => '',
        'lpres_migration.manifest' => '',
        'lpres_migration.media_directory' => '',
    ]);

    $problems = MigrationPaths::fromConfig()->problems();

    expect($problems)->toHaveCount(3)
        ->and(implode(' ', $problems))->toContain('LPRES_MIGRATION_XML')
        ->and(implode(' ', $problems))->toContain('LPRES_MIGRATION_MANIFEST')
        ->and(implode(' ', $problems))->toContain('LPRES_MIGRATION_MEDIA_DIR');
});
