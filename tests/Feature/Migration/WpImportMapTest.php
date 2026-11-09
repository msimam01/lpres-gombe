<?php

use App\Models\News;
use App\Models\WpImportMap;
use Illuminate\Database\QueryException;

function mapping(array $attributes = []): WpImportMap
{
    return WpImportMap::create($attributes + [
        'source_type' => 'post',
        'source_id' => 5907,
        'target_type' => null,
        'target_id' => null,
        'source_hash' => str_repeat('a', 64),
        'imported_at' => null,
    ]);
}

test('it stores a mapping with typed source and target ids', function () {
    $map = mapping();

    expect($map->source_id)->toBe(5907)
        ->and($map->source_type)->toBe('post')
        ->and($map->target_id)->toBeNull()
        ->and($map->isImported())->toBeFalse();
});

test('a wordpress record can only be mapped once', function () {
    mapping();

    expect(fn () => mapping(['target_type' => News::class]))
        ->toThrow(QueryException::class);
});

test('the same source id may exist for different source types', function () {
    mapping(['source_type' => 'post', 'source_id' => 1]);
    mapping(['source_type' => 'page', 'source_id' => 1]);
    mapping(['source_type' => 'attachment', 'source_id' => 1]);

    expect(WpImportMap::query()->count())->toBe(3);
});

test('lookup finds a mapping by source type and id', function () {
    $map = mapping();

    expect(WpImportMap::lookup('post', 5907)?->id)->toBe($map->id)
        ->and(WpImportMap::lookup('post', '5907')?->id)->toBe($map->id)
        ->and(WpImportMap::lookup('page', 5907))->toBeNull();
});

test('it reports whether the source changed since import', function () {
    $map = mapping(['source_hash' => str_repeat('a', 64)]);

    expect($map->hasSourceChanged(str_repeat('a', 64)))->toBeFalse()
        ->and($map->hasSourceChanged(str_repeat('b', 64)))->toBeTrue();
});

test('an unknown hash never counts as a change', function () {
    $withoutHash = mapping(['source_type' => 'post', 'source_id' => 1, 'source_hash' => null]);
    $withHash = mapping(['source_type' => 'post', 'source_id' => 2]);

    expect($withoutHash->hasSourceChanged(str_repeat('b', 64)))->toBeFalse()
        ->and($withHash->hasSourceChanged(null))->toBeFalse();
});

test('it resolves the imported laravel record', function () {
    $news = News::create([
        'title' => 'Trains 67 Farmers',
        'slug' => 'trains-67-farmers',
        'body' => 'Body',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $map = mapping([
        'target_type' => News::class,
        'target_id' => $news->id,
        'imported_at' => now(),
    ]);

    expect($map->target)->toBeInstanceOf(News::class)
        ->and($map->target->id)->toBe($news->id)
        ->and($map->isImported())->toBeTrue();
});

test('it separates reserved mappings from imported ones', function () {
    mapping(['source_type' => 'post', 'source_id' => 1, 'imported_at' => null]);
    mapping(['source_type' => 'post', 'source_id' => 2, 'imported_at' => now()]);

    expect(WpImportMap::query()->pending()->count())->toBe(1)
        ->and(WpImportMap::query()->imported()->count())->toBe(1)
        ->and(WpImportMap::query()->forSource('post', 2)->count())->toBe(1);
});
