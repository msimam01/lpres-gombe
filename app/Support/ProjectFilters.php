<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Resolved public project filters. Invalid values are ignored rather than
 * rejected: a shared link with a stale LGA or category must still show the
 * full, unfiltered listing.
 */
final class ProjectFilters
{
    /** @return array{lga: string|null, category: string|null} */
    public static function fromRequest(Request $request): array
    {
        return [
            'lga' => self::validLga($request->query('lga')),
            'category' => self::validCategory($request->query('category')),
        ];
    }

    /** @param  array{lga: string|null, category: string|null}  $filters */
    public static function apply(Builder $query, array $filters): Builder
    {
        if ($filters['lga'] !== null) {
            $query->where('projects.lga', $filters['lga']);
        }

        if ($filters['category'] !== null) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }

        return $query;
    }

    /** @param  array{lga: string|null, category: string|null}  $filters */
    public static function queryString(array $filters): array
    {
        return array_filter($filters, fn (?string $value) => $value !== null);
    }

    private static function validLga(mixed $value): ?string
    {
        return is_string($value) && in_array($value, config('lpres.lgas', []), true) ? $value : null;
    }

    private static function validCategory(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Project::query()
            ->whereHas('category', fn (Builder $query) => $query->where('slug', $value))
            ->exists() ? $value : null;
    }
}
