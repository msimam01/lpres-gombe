<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\MediaItem;
use Closure;
use Illuminate\Http\Request;

/**
 * Dedicated "Add Image" workflow: a standalone or content-associated image
 * item (uploads use the shared public-disk storage architecture). Video items
 * are created through their own controller; the boxes never share a form.
 */
class MediaImageAdminController extends MediaModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: MediaItem::class,
            routeBase: 'media/images',
            label: 'Media image',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'caption', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'image_path', 'label' => 'Image', 'type' => 'image', 'removable' => true],
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
            ],
        );
    }

    protected function validatePayload(Request $request, ModuleConfig $config, ?int $ignoreId = null): array
    {
        $data = parent::validatePayload($request, $config, $ignoreId);

        // Creation requires an uploaded image. Editing may keep the current
        // image (no upload) or remove it explicitly via the "remove current
        // image" action — an existing item stays valid either way.
        $hasImage = $request->hasFile('image_path')
            || ($ignoreId !== null && filled(MediaItem::query()->findOrFail($ignoreId)->image_path)
                && ! $request->boolean('remove_image_path'));

        if (! $hasImage) {
            $request->validate([
                'image_path' => [
                    function (string $attribute, mixed $value, Closure $fail) {
                        $fail('An image item requires an image.');
                    },
                ],
            ]);
        }

        $data['media_type'] = MediaItem::TYPE_IMAGE;

        return $data;
    }
}
