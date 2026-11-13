<?php

namespace App\Support;

use App\Models\Project;

/**
 * Single source of truth for what a project's location may expose publicly.
 *
 * Actual coordinates live only in `projects.latitude`/`longitude`. Everything
 * public (map markers, detail page, directions) goes through this class so the
 * visibility rules cannot drift between controllers or the frontend.
 */
final class ProjectLocation
{
    public const EXACT = 'exact';
    public const APPROXIMATE = 'approximate';
    public const LGA_ONLY = 'lga_only';
    public const HIDDEN = 'hidden';

    /**
     * The coordinate a visitor may see, or null when the project must not be
     * placed on a map at all.
     *
     * @return array{latitude: float, longitude: float, precision: string}|null
     */
    public function publicCoordinates(Project $project): ?array
    {
        if (! $project->hasCoordinates()) {
            return null;
        }

        return match ($project->location_visibility) {
            self::EXACT => [
                'latitude' => (float) $project->latitude,
                'longitude' => (float) $project->longitude,
                'precision' => self::EXACT,
            ],
            self::APPROXIMATE => $this->approximate($project),
            // lga_only and hidden never expose a point.
            default => null,
        };
    }

    /**
     * Directions always use the public coordinate. For approximate projects
     * that is the displaced point, never the stored one.
     *
     * @param  array{latitude: float, longitude: float}|null  $public
     */
    public function directionsUrl(?array $public): ?string
    {
        if ($public === null) {
            return null;
        }

        return sprintf(
            'https://www.google.com/maps/dir/?api=1&destination=%s,%s',
            number_format($public['latitude'], 7, '.', ''),
            number_format($public['longitude'], 7, '.', ''),
        );
    }

    /**
     * What the project detail page may render. No raw latitude/longitude ever
     * leaves this method: only `coordinates`, which is already public.
     *
     * @return array{
     *     visibility: string,
     *     lga: string|null,
     *     ward: string|null,
     *     description: string|null,
     *     coordinates: array{latitude: float, longitude: float, precision: string}|null,
     *     directionsUrl: string|null,
     *     isApproximate: bool
     * }
     */
    public function forDisplay(Project $project): array
    {
        $visibility = in_array($project->location_visibility, [
            self::EXACT, self::APPROXIMATE, self::LGA_ONLY, self::HIDDEN,
        ], true) ? $project->location_visibility : self::HIDDEN;

        $coordinates = $this->publicCoordinates($project);
        $showsArea = in_array($visibility, [self::EXACT, self::APPROXIMATE, self::LGA_ONLY], true);
        $showsPin = $coordinates !== null;

        return [
            'visibility' => $visibility,
            'lga' => $showsArea ? $project->lga : null,
            // Ward and the landmark description are only meaningful alongside
            // a public point; lga_only deliberately stops at the LGA.
            'ward' => $showsPin ? $project->ward : null,
            'description' => $showsPin ? $project->address : null,
            'coordinates' => $coordinates,
            'directionsUrl' => $this->directionsUrl($coordinates),
            'isApproximate' => ($coordinates['precision'] ?? null) === self::APPROXIMATE,
        ];
    }

    /**
     * Deterministic, keyed displacement of the stored point.
     *
     * HMAC over the project id yields a stable bearing and distance inside the
     * configured range, so the marker never jumps between requests and the
     * displacement cannot be inverted without the application key.
     *
     * @return array{latitude: float, longitude: float, precision: string}
     */
    private function approximate(Project $project): array
    {
        $lat = (float) $project->latitude;
        $lng = (float) $project->longitude;

        $min = max(1, (int) config('lpres.public_map.approximate.min_meters', 500));
        $max = max($min, (int) config('lpres.public_map.approximate.max_meters', 1500));

        $digest = hash_hmac(
            'sha256',
            'project-public-location:'.$project->id,
            (string) config('app.key'),
            true,
        );

        $bearingUnit = unpack('N', substr($digest, 0, 4))[1] / 0xFFFFFFFF;
        $distanceUnit = unpack('N', substr($digest, 4, 4))[1] / 0xFFFFFFFF;

        $bearing = $bearingUnit * 2 * M_PI;
        $distance = $min + ($distanceUnit * ($max - $min));

        $latitudeOffset = ($distance * cos($bearing)) / 111320.0;
        $longitudeOffset = ($distance * sin($bearing)) / (111320.0 * max(cos(deg2rad($lat)), 0.01));

        return [
            'latitude' => round($lat + $latitudeOffset, 7),
            'longitude' => round($lng + $longitudeOffset, 7),
            'precision' => self::APPROXIMATE,
        ];
    }
}
