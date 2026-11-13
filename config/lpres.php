<?php

/*
|--------------------------------------------------------------------------
| Public basemap credentials
|--------------------------------------------------------------------------
|
| Resolved once here so no keyed URL is ever hand-maintained (or committed)
| in the repository. Thunderforest tile keys are public by necessity: the
| browser requests the tiles, so the key is visible in those requests. Treat
| it as a domain-restricted key, not a server secret. An empty key simply
| leaves the public map without imagery; markers, filters and listings keep
| working.
|
*/

$thunderforestApiKey = trim((string) env('THUNDERFOREST_API_KEY', ''));
$thunderforestStyle = trim((string) env('THUNDERFOREST_STYLE', 'atlas')) ?: 'atlas';

return [

    /*
    |--------------------------------------------------------------------------
    | Approved Local Government Areas
    |--------------------------------------------------------------------------
    |
    | The single application-level source for Gombe State LGA names. Admin
    | selects, validation rules and (later) public filters all read this list,
    | so the names never drift between PHP and React.
    |
    */

    'lgas' => [
        'Akko',
        'Balanga',
        'Billiri',
        'Dukku',
        'Funakaye',
        'Gombe',
        'Kaltungo',
        'Kwami',
        'Nafada',
        'Shongom',
        'Yamaltu/Deba',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public location visibility
    |--------------------------------------------------------------------------
    |
    | Stored per project in `projects.location_visibility`. The key is the
    | stored value; the label is what administrators see. "Hidden" is first so
    | new projects default to the least exposing option.
    |
    */

    'location_visibilities' => [
        'hidden' => 'Hidden — no public location',
        'lga_only' => 'LGA only — show the local government area, no coordinates',
        'approximate' => 'Approximate — show a nearby, deliberately displaced position',
        'exact' => 'Exact — show the precise position',
    ],

    'location_visibility_default' => 'hidden',

    /*
    |--------------------------------------------------------------------------
    | Public map
    |--------------------------------------------------------------------------
    |
    | Tile provider and initial centre are configuration, never committed
    | credentials. The default centre is the Gombe city centre published by
    | GeoNames / OpenStreetMap (node 501540886): 10.2841 N, 11.1653 E. Override
    | it via PUBLIC_MAP_CENTER_LAT / PUBLIC_MAP_CENTER_LNG if the State
    | provides an official centre.
    |
    | The approved provider is Thunderforest (Atlas style), whose tile URL is
    | built here from THUNDERFOREST_API_KEY. Keep PUBLIC_MAP_TILE_URL empty to
    | use that provider; it remains an override for a different approved
    | source. The public site must never use the OSM community tile servers as
    | a production basemap. With no provider configured the map renders markers
    | on a plain canvas together with a clear notice.
    |
    | Thunderforest requires the attribution text below; PUBLIC_MAP_ATTRIBUTION
    | overrides it, which any replacement provider needs.
    |
    */

    'public_map' => [
        'tileUrl' => env('PUBLIC_MAP_TILE_URL') ?: ($thunderforestApiKey !== ''
            ? 'https://api.thunderforest.com/'.$thunderforestStyle.'/{z}/{x}/{y}.png?apikey='.$thunderforestApiKey
            : null),
        'attribution' => env('PUBLIC_MAP_ATTRIBUTION', 'Maps &copy; Thunderforest, Data &copy; OpenStreetMap contributors'),
        'center' => [
            'lat' => (float) env('PUBLIC_MAP_CENTER_LAT', 10.2841),
            'lng' => (float) env('PUBLIC_MAP_CENTER_LNG', 11.1653),
        ],
        'zoom' => (int) env('PUBLIC_MAP_ZOOM', 8),

        // Atlas is raster up to zoom 22; 19 keeps detail sharp without
        // over-fetching, and a finite maximum is required by the cluster plugin.
        'minZoom' => 3,
        'maxZoom' => 19,

        // Deterministic displacement applied to "approximate" projects.
        'approximate' => [
            'min_meters' => 500,
            'max_meters' => 1500,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Administrator map picker
    |--------------------------------------------------------------------------
    |
    | Used only by the authenticated admin location picker: a handful of staff
    | editing one project at a time, i.e. light use of the OSM community tile
    | service. The public map above has its own provider setting and must never
    | reuse these community tiles in production.
    |
    | Keys are the contract with resources/js/components/admin/location-picker.tsx.
    */

    'admin_map' => [
        'tileUrl' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'center' => ['lat' => 10.2897, 'lng' => 11.1673],
        'zoom' => 8,
    ],

];
