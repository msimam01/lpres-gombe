<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generic, configuration-driven CRUD controller for the L-PRES content
 * modules. Every subclass only supplies its ModuleConfig; list/form/save/
 * publish/unpublish/delete behaviour is shared and always runs behind the
 * `auth` middleware. File uploads are validated and stored on the public disk.
 */
abstract class AdminModuleController extends Controller
{
    /** Upload validation rules per field type. */
    public const FILE_RULES = [
        'image' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        'document' => 'file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        'video' => 'file|mimes:mp4,webm|max:102400',
    ];

    abstract protected function config(): ModuleConfig;

    public function index(): Response
    {
        $config = $this->config();
        $model = $config->model;

        $rows = $model::query()
            ->orderByDesc('id')
            ->paginate($config->perPage)
            ->through(fn ($record) => [
                'id' => $record->id,
                'label' => (string) $record->{$config->labelColumn()},
                'slug' => $record->slug ?? null,
                'status' => $record->status,
                'updated_at' => $record->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('admin/resource/index', [
            'module' => $config->toFrontend(),
            'rows' => $rows,
        ]);
    }

    public function create(): Response
    {
        $config = $this->config();

        return Inertia::render('admin/resource/form', [
            'module' => $config->toFrontend(),
            'record' => $this->emptyRecord($config),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();
        $model = $config->model;

        $data = $this->validatePayload($request, $config);
        $data = $this->applySlug($config, $data);

        $record = $model::create($data);
        $this->storeFiles($request, $config, $record);

        return redirect()
            ->route('admin.'.str_replace('/', '.', $config->routeBase).'.index')
            ->with('success', $config->label.' created.');
    }

    public function edit(int $id): Response
    {
        $config = $this->config();
        $record = $config->model::query()->findOrFail($id);

        return Inertia::render('admin/resource/form', [
            'module' => $config->toFrontend(),
            'record' => $record->only($config->fillableKeys()) + ['id' => $record->id],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $config = $this->config();
        $record = $config->model::query()->findOrFail($id);

        // Slugs never change on update so public URLs stay bookmarkable.
        // The id passes to validation so "existing file, no new upload"
        // edits are recognised instead of treated like a bare create.
        $data = $this->validatePayload($request, $config, $id);
        $data = collect($data)->put('slug', $record->slug)->all();

        $record->update($data);
        $this->storeFiles($request, $config, $record);

        return redirect()
            ->route('admin.'.str_replace('/', '.', $config->routeBase).'.index')
            ->with('success', $config->label.' updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $config = $this->config();
        $config->model::query()->findOrFail($id)->delete();

        return redirect()->back()->with('success', $config->label.' deleted.');
    }

    /** Publish / unpublish. Works on boolean and draft/published models. */
    public function toggle(Request $request, int $id): RedirectResponse
    {
        $config = $this->config();
        $record = $config->model::query()->findOrFail($id);

        $newStatus = is_bool($record->status)
            ? ! $record->status
            : ($record->status === 'published' ? 'draft' : 'published');

        $record->update(['status' => $newStatus]);

        return redirect()->back()->with('success', $config->label.' visibility updated.');
    }

    // ----------------------------------------------------------------------

    protected function validationRules(ModuleConfig $config, ?int $ignoreId = null): array
    {
        $rules = [];

        foreach ($config->fields as $field) {
            $required = ($field['required'] ?? false) ? 'required' : 'nullable';

            $rules[$field['key']] = match ($field['type']) {
                'text' => [$required, 'string', 'max:255'],
                'textarea' => [$required, 'string'],
                'select' => [$required, 'string', Rule::in(array_keys($field['options'] ?? []))],
                'boolean' => ['sometimes', 'boolean'],
                'integer' => ['sometimes', 'integer', 'min:0'],
                'date' => ['nullable', 'date'],
                'image', 'document', 'video' => array_merge(
                    [$required], explode('|', self::FILE_RULES[$field['type']])
                ),
                default => ['nullable'],
            };

            // The "remove current file" checkbox for removable image fields is
            // bookkeeping, never a stored column.
            if (($field['removable'] ?? false) === true && $field['type'] === 'image') {
                $rules['remove_'.$field['key']] = ['sometimes', 'boolean'];
            }

            // Gallery pseudo-fields never map to columns by themselves: the
            // kept paths + uploaded files are merged in storeFiles.
            if ($field['type'] === 'gallery') {
                $rules['gallery_'.$field['key']] = ['nullable', 'array'];
                $rules['gallery_'.$field['key'].'.*'] = ['nullable', 'string', 'max:500'];
                $rules['files_'.$field['key']] = ['nullable', 'array'];
                $rules['files_'.$field['key'].'.*'] = explode('|', self::FILE_RULES['image']);
                unset($rules[$field['key']]);

                continue;
            }

            // Repeatable pseudo-fields (e.g. cluster beneficiary rows) are
            // validated by their own controller; keep them out of the rules
            // built from the config here.
            if ($field['type'] === 'beneficiary-table') {
                unset($rules[$field['key']]);
            }
        }

        return $rules;
    }

    protected function validatePayload(Request $request, ModuleConfig $config, ?int $ignoreId = null): array
    {
        $validated = $request->validate($this->validationRules($config, $ignoreId));

        // Optional selects (e.g. "no category yet") submit an empty string
        // from the form; normalise that to a proper null relation value.
        foreach ($config->fields as $field) {
            if ($field['type'] === 'select'
                && ($field['required'] ?? false) === false
                && isset($validated[$field['key']])
                && $validated[$field['key']] === '') {
                $validated[$field['key']] = null;
            }
        }

        $data = collect($validated)->only($config->fillableKeys())->all();

        // Unchecked boolean fields are simply absent from the payload.
        foreach ($config->fields as $field) {
            if ($field['type'] === 'boolean' && ! array_key_exists($field['key'], $data)) {
                $data[$field['key']] = false;
            }
        }

        // Remove-bookkeeping and pseudo-fields aren't columns: strip them
        // from the data that gets mass-assigned (storeFiles reads the request).
        foreach ($config->fields as $field) {
            if (($field['removable'] ?? false) === true) {
                unset($data['remove_'.$field['key']]);
            }
            if (in_array($field['type'], ['beneficiary-table', 'gallery'], true)) {
                unset($data[$field['key']]);
            }
        }

        // File fields never mass-assign, on create or update:
        //  - a valid upload is persisted by storeFiles() (so the record write
        //    never serialises an UploadedFile),
        //  - a stale stored-path string replayed by an edit form is dropped
        //    instead of failing an image rule or overwriting the column,
        //  - when nothing is uploaded and no removal is requested, the
        //    existing stored path is simply left untouched.
        foreach ($config->fields as $field) {
            if (in_array($field['type'], ['image', 'document', 'video'], true)) {
                unset($data[$field['key']]);
            }
        }

        return $data;
    }

    protected function storeFiles(Request $request, ModuleConfig $config, $record): void
    {
        foreach ($config->fields as $field) {
            if ($field['type'] === 'gallery') {
                $this->storeGallery($request, $config, $record, $field['key']);

                continue;
            }

            if (! in_array($field['type'], ['image', 'document', 'video'], true)) {
                continue;
            }

            $key = $field['key'];

            // "Remove" checkbox (removable image fields): drop the stored file
            // and null the column once the record no longer references it.
            if (($field['removable'] ?? false) === true
                && (bool) $request->boolean('remove_'.$key)
                && ! $request->hasFile($key)) {
                $this->forgetFile($record, $key);

                continue;
            }

            if (! $request->hasFile($key)) {
                continue;
            }

            // Keep the old path until the new file is stored, then release it
            // if (and only if) nothing else still references it.
            $previous = $record->{$key} ?? null;

            $path = $request->file($key)
                ->store('uploads/'.$config->routeBase, 'public');

            $record->forceFill([$key => $path])->save();

            if ($previous && $previous !== $path) {
                $this->releaseFile($previous);
            }
        }
    }

    /**
     * Multi-image gallery: the form submits the retained paths it kept
     * (gallery_{key}) and any newly picked files (files_{key}); the merged
     * list replaces the record's gallery, releasing dropped files that no
     * other record references.
     */
    protected function storeGallery(Request $request, ModuleConfig $config, $record, string $key): void
    {
        // Synchronise the gallery when the edit form submitted it in any of
        // the three shapes the React GalleryField produces: kept paths, new
        // file uploads, or the "gallery edited" sentinel. An untouched
        // record keeps its images; deleting every image clears the column.
        $submitted = $request->exists('gallery_'.$key)
            || $request->hasFile('files_'.$key)
            || filled($request->input('gallery_has_'.$key));

        if (! $submitted) {
            return; // Form without a gallery section: leave records untouched.
        }

        $existing = collect($record->{$key} ?? [])->values()->all();
        // The hidden "kept paths" inputs send empty strings for untouched
        // slots; those are not images and must not pollute the gallery.
        $kept = collect($request->input('gallery_'.$key, [])
            ?: [])->filter(fn ($path) => filled($path))->values()->all();

        $uploaded = collect($request->file('files_'.$key, []))
            ->filter()
            ->map(fn ($file) => $file->store('uploads/'.$config->routeBase, 'public'))
            ->values()
            ->all();

        $record->forceFill([$key => array_values(array_merge($kept, $uploaded))])->save();

        foreach (array_diff($existing, $kept, $uploaded) as $dropped) {
            if (! in_array($dropped, $uploaded, true)) {
                $this->releaseFile($dropped);
            }
        }
    }

    /**
     * Null a file column and delete the underlying file, guarding against
     * deleting a file another published record still references.
     */
    protected function forgetFile($record, string $column): void
    {
        $path = $record->{$column} ?? null;

        if (filled($path)) {
            $record->forceFill([$column => null])->save();
            $this->releaseFile($path);
        }
    }

    /**
     * Delete an upload from the public disk only when no other record in the
     * content tables holds the same path.
     */
    protected function releaseFile(string $path): void
    {
        $tables = [
            'news' => ['featured_image_path', 'gallery_image_paths'],
            'events' => ['image_path', 'gallery_image_paths'],
            'projects' => ['featured_image_path', 'gallery_image_paths'],
            'project_components' => ['image_path'],
            'team_members' => ['photograph_path'],
            'value_chain_clusters' => ['image_path'],
            'visual_reports' => ['image_path', 'file_path'],
            'media_items' => ['image_path', 'thumbnail_path', 'gallery_image_paths'],
            'hero_slides' => ['image_path'],
            'gallery_images' => ['path'],
            'site_settings' => null, // key/value table handled below
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($columns === null) {
                if (DB::table($table)->where('key', 'logo_path')->where('value', $path)->exists()) {
                    return;
                }

                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                // gallery_image_paths is a JSON array column.
                if ($column === 'gallery_image_paths') {
                    if (DB::table($table)->whereJsonContains($column, $path)->exists()) {
                        return;
                    }

                    continue;
                }

                if (DB::table($table)->where($column, $path)->exists()) {
                    return;
                }
            }
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * On create, derive the slug from the configured source column
     * (deduplicated); on update the caller pins the existing slug.
     */
    protected function applySlug(ModuleConfig $config, array $data): array
    {
        $model = $config->model;
        $fillables = (new $model)->getFillable();

        if (! in_array('slug', $fillables, true)) {
            unset($data['slug']);

            return $data;
        }

        $source = $data[$config->titleFrom ?? 'title'] ?? null;
        $slug = $source ? Str::slug(Str::limit($source, 120, '')) : Str::lower(Str::random(12));

        $base = $slug;
        $attempt = 2;
        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$attempt++;
        }

        $data['slug'] = $slug;

        return $data;
    }

    protected function emptyRecord(ModuleConfig $config): array
    {
        return collect($config->fields)->mapWithKeys(
            fn (array $f) => [
                $f['key'] => match ($f['type']) {
                    'boolean' => false,
                    'integer' => 0,
                    // Optional selects (e.g. "no category yet") stay empty on create;
                    // required selects default to their first real option.
                    'select' => ($f['required'] ?? false) ? array_key_first($f['options'] ?? []) : '',
                    default => $f['default'] ?? '',
                },
            ]
        )->all();
    }
}
