<?php

namespace App\Admin;

use Illuminate\Support\Arr;

/**
 * Declarative description of a content module: which model it manages, which
 * fields the admin form presents, which validation rules apply and which
 * columns the index table shows. Consumed by the generic AdminModuleController
 * and forwarded to the admin React pages.
 */
class ModuleConfig
{
    public function __construct(
        public string $model,
        public string $routeBase,
        public string $label,          // e.g. "News articles"
        public array $fields,          // see AdminModuleController::fieldDefinitions
        public int $perPage = 20,
        public ?string $titleFrom = null, // column the slug is generated from
        public ?string $labelFrom = null, // column shown as the record name
    ) {}

    public function routeNames(): array
    {
        return ['index', 'create', 'store', 'edit', 'update', 'destroy', 'toggle'];
    }

    public function field(string $key): ?array
    {
        return Arr::first($this->fields, fn (array $field) => $field['key'] === $key);
    }

    /**
     * Column displayed as the record name in the admin table.
     */
    public function labelColumn(): string
    {
        return $this->labelFrom
            ?? $this->titleFrom
            ?? collect($this->fields)->firstWhere(fn (array $f) => $f['type'] === 'text')['key']
            ?? 'id';
    }

    /**
     * All keys this controller is allowed to persist (slug always wins).
     */
    public function fillableKeys(): array
    {
        $keys = collect($this->fields)->pluck('key')->all();

        if (in_array('slug', (new $this->model)->getFillable(), true)) {
            $keys[] = 'slug';
        }

        return array_values(array_unique($keys));
    }

    /**
     * Whether this module manages a publication/visibility control. Renders
     * the Visibility column and Publish/Unpublish actions in the admin table.
     */
    public function hasStatus(): bool
    {
        return in_array('status', collect($this->fields)->pluck('key')->all(), true);
    }

    public function toFrontend(): array
    {
        $frontend = [
            'model' => $this->model,
            'routeBase' => $this->routeBase,
            'label' => $this->label,
            'titleFrom' => $this->titleFrom,
            'labelFrom' => $this->labelFrom,
            'fields' => $this->fields,
        ];

        return $frontend + ['hasStatus' => $this->hasStatus()];
    }
}
