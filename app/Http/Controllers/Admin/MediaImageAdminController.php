<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\MediaItem;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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

    /**
     * "Add images": one form that accepts several files at once. Every file
     * becomes its own image item so the existing single-item edit/delete
     * workflow keeps working unchanged.
     */
    public function bulkCreate(): Response
    {
        return Inertia::render('admin/media/bulk-images', [
            'associations' => $this::associationOptions(),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:30'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'caption' => ['nullable', 'string'],
            'associated' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['draft', 'published'])],
        ]);

        [$associatedType, $associatedId] = $this->parseAssociation($validated['associated'] ?? null);
        $status = $validated['status'] ?? 'published';
        $caption = $validated['caption'] ?? null;

        $created = 0;

        foreach ($request->file('images', []) as $file) {
            $title = Str::title(str_replace(['-', '_'], ' ', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)));

            MediaItem::create([
                'title' => Str::limit(trim($title) !== '' ? trim($title) : 'Media image', 120, ''),
                'caption' => $caption,
                'image_path' => $file->store('uploads/media/images', 'public'),
                'media_type' => MediaItem::TYPE_IMAGE,
                'associated_type' => $associatedType,
                'associated_id' => $associatedId,
                'status' => $status,
            ]);

            $created++;
        }

        return redirect()
            ->route('admin.media.index')
            ->with('success', $created.' image'.($created === 1 ? '' : 's').' added.');
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
