<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Gallery;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dedicated Galleries workflow: a gallery is a titled, publishable image
 * collection that may stand alone or be associated with one event, news
 * article or project record. Images live in gallery_images in display
 * order; editing keeps untouched images and adds/removes only what changed.
 */
class MediaGalleryAdminController extends MediaModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: Gallery::class,
            routeBase: 'media/galleries',
            label: 'Gallery',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Gallery title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                [
                    'key' => 'associated',
                    'label' => 'Associated content',
                    'type' => 'select',
                    'options' => $this::associationOptions(),
                    'manageLabel' => 'No associated content',
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['draft' => 'Draft', 'published' => 'Published'],
                ],
                ['key' => 'images', 'label' => 'Gallery images', 'type' => 'gallery'],
            ],
        );
    }

    /**
     * Edit form carries the saved images so the form shows the existing
     * collection and submits the kept paths back unchanged.
     */
    public function edit(int $id): Response
    {
        $config = $this->config();
        $record = $config->model::query()->with('images')->findOrFail($id);

        $data = collect($record->only($config->fillableKeys()))
            ->put('images', $record->images->pluck('path')->all())
            ->put('id', $record->id)
            ->all();

        return Inertia::render('admin/resource/form', [
            'module' => $config->toFrontend(),
            'record' => $data,
        ]);
    }

    /**
     * Gallery images sync (replaces the base JSON-column behaviour): kept
     * paths are written first, new uploads appended, rows outside the merged
     * list deleted, and dropped files released only when nothing else on the
     * site references them.
     */
    protected function storeGallery(Request $request, ModuleConfig $config, $record, string $key): void
    {
        $inputKey = 'gallery_'.$key;

        // Same three shapes the React GalleryField produces — kept paths,
        // new uploads, or the "gallery edited" sentinel — so an empty,
        // emptied or untouched gallery is handled correctly.
        if (! $request->exists($inputKey)
            && ! $request->hasFile('files_'.$key)
            && ! filled($request->input('gallery_has_'.$key))) {
            return; // Form without a gallery section: leave images untouched.
        }

        $existing = $record->images()->pluck('path')->all();

        $kept = collect($request->input($inputKey, []) ?: [])
            ->filter(fn ($path) => filled($path))
            ->unique()
            ->values()
            ->all();

        $uploaded = collect($request->file('files_'.$key, []))
            ->filter()
            ->map(fn ($file) => $file->store('uploads/media/galleries', 'public'))
            ->values()
            ->all();

        // Deterministic rebuild: kept rows first (in submitted order), then
        // the newly uploaded ones.
        $final = array_values(array_merge($kept, array_diff($uploaded, $kept)));

        foreach ($final as $sortIndex => $path) {
            GalleryImage::query()->updateOrCreate(
                ['gallery_id' => $record->id, 'path' => $path],
                ['sort_index' => $sortIndex],
            );
        }

        $record->images()->whereNotIn('path', $final)->delete();

        foreach (array_diff($existing, $kept, $uploaded) as $dropped) {
            $this->releaseFile($dropped);
        }
    }
}
