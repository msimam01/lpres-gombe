<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Event;
use App\Models\MediaItem;
use App\Models\News;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the redesigned media modules (Image / Video /
 * Gallery). Every module supports the optional "associated content" select
 * that ties the item to one event, news article or project record — or
 * leaves it standalone.
 */
abstract class MediaModuleController extends AdminModuleController
{
    /**
     * Select options for associating media with content. The submitted value
     * is "type:id" (or "" for a standalone item) and is parsed into the
     * associated_type / associated_id columns.
     */
    public static function associationOptions(): array
    {
        $options = ['' => 'Standalone (no association)'];

        foreach (MediaItem::ASSOCIATIONS as $type => $class) {
            foreach ($class::query()->orderBy('id')->get(['id', 'title']) as $record) {
                $label = match (true) {
                    $class === Event::class => 'Event',
                    $class === News::class => 'News',
                    default => 'Project',
                };

                $options[$type.':'.$record->id] = $label.': '.$record->title;
            }
        }

        return $options;
    }

    /** Final validation: turn `associated` into the two columns. */
    protected function validatePayload(Request $request, ModuleConfig $config, ?int $ignoreId = null): array
    {
        $data = parent::validatePayload($request, $config, $ignoreId);

        [$associatedType, $associatedId] = $this->parseAssociation($data['associated'] ?? null);
        unset($data['associated']);

        $data['associated_type'] = $associatedType;
        $data['associated_id'] = $associatedId;

        return $data;
    }

    /** @return array{0: ?string, 1: ?int} */
    private function parseAssociation(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [null, null];
        }

        $parts = explode(':', $value, 2);
        $type = $parts[0] ?? null;
        $id = isset($parts[1]) ? filter_var($parts[1], FILTER_VALIDATE_INT) : null;

        // Only a known type with a real record resolves; anything else is a
        // malformed or stale option and stores as standalone instead.
        if (! isset(MediaItem::ASSOCIATIONS[$type]) || $id === null || $id <= 0) {
            return [null, null];
        }

        if (! MediaItem::ASSOCIATIONS[$type]::query()->whereKey($id)->exists()) {
            return [null, null];
        }

        return [$type, $id];
    }
}
