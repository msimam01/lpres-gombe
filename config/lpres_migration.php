<?php

/*
|--------------------------------------------------------------------------
| WordPress migration sources
|--------------------------------------------------------------------------
|
| Paths to the audited WordPress artefacts used by `lpres:import-wordpress`.
| Defaults resolve inside this repository so a fresh checkout works without
| any environment configuration; point the environment variables at another
| location when the export or media live elsewhere.
|
| An empty or missing path is reported as an error by the command. Nothing is
| ever substituted silently — see docs/lpres-migration-audit/08-import-infrastructure.md.
|
*/

return [

    /*
    | WXR export produced by Tools -> Export in the WordPress admin.
    */
    'xml' => env(
        'LPRES_MIGRATION_XML',
        base_path('docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml'),
    ),

    /*
    | CSV manifest describing every downloaded media URL (url, relative_path,
    | referenced_by, status, http_status, bytes).
    */
    'manifest' => env(
        'LPRES_MIGRATION_MANIFEST',
        base_path('docs/lpres-design-references/lpres-migration-media/media-manifest.csv'),
    ),

    /*
    | Root of the downloaded media tree. The manifest's `relative_path` values
    | are resolved against this directory, never against the current working
    | directory.
    */
    'media_directory' => env(
        'LPRES_MIGRATION_MEDIA_DIR',
        base_path('docs/lpres-design-references/lpres-migration-media'),
    ),

    /*
    | Audited content inventory (report 02). The importer takes eligibility
    | from this file — `publish` + classification `news` + in migration scope —
    | rather than guessing from titles or slugs. It is read, never written.
    */
    'inventory' => env(
        'LPRES_MIGRATION_INVENTORY',
        base_path('docs/lpres-migration-audit/02-content-inventory.csv'),
    ),

    /*
    | Scratch space for later phases (parse dumps, import logs). The current
    | phase never creates or writes this directory.
    */
    'working_directory' => env(
        'LPRES_MIGRATION_WORK_DIR',
        storage_path('app/private/lpres-migration'),
    ),

    /*
    | Filesystem disk that imported media will be copied onto once the importer
    | itself is implemented. Declared here so the storage target is explicit
    | and reviewable before any file moves.
    */
    'media_disk' => env('LPRES_MIGRATION_MEDIA_DISK', 'public'),

];
