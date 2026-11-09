<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\MediaItem;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dedicated "Add Video" workflow. Videos come from YouTube: the form
 * validates the URL, is never accepting arbitrary embed HTML, and stores the
 * canonical watch URL. The embed URL is generated only in trusted helpers at
 * render time (server or the public page's strict parser) — never stored
 * from user input.
 */
class MediaVideoAdminController extends MediaModuleController
{
    private const YOUTUBE_PATTERN = '#^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,63})(?:[?&/].*)?$#';

    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: MediaItem::class,
            routeBase: 'media/videos',
            label: 'Video',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Video title', 'type' => 'text', 'required' => true],
                ['key' => 'caption', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'video_url', 'label' => 'YouTube URL', 'type' => 'text', 'required' => true],
                ['key' => 'thumbnail_path', 'label' => 'Custom thumbnail (optional — YouTube thumbnail used otherwise)', 'type' => 'image', 'removable' => true],
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

        $data['video_url'] = $request->validate([
            'video_url' => [
                'required', 'string', 'max:500',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! preg_match(self::YOUTUBE_PATTERN, (string) $value)) {
                        $fail('The video URL must be a YouTube link (youtube.com/watch, youtu.be or youtube.com/embed).');
                    }
                },
            ],
        ])['video_url'] ?? null;

        // Store the canonical watch URL; the identifier is extracted from this
        // format and never from raw markup supplied by the user.
        if ($data['video_url'] !== null) {
            preg_match(self::YOUTUBE_PATTERN, $data['video_url'], $matches);
            $data['video_url'] = 'https://www.youtube.com/watch?v='.$matches[1];
        }

        $data['media_type'] = MediaItem::TYPE_VIDEO;

        return $data;
    }

    /**
     * Videos never upload a media file: clear any stale stored video file
     * references from before the redesign without touching uploaded images.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = null;
        $config = $this->config();

        $record = new ($config->model)(collect($this->validatePayload($request, $config))->only([
            'title', 'caption', 'video_url', 'thumbnail_path', 'status', 'associated_type', 'associated_id',
        ])->all());
        $record->forceFill(['media_type' => MediaItem::TYPE_VIDEO])->save();
        $this->storeFiles($request, $config, $record);

        return redirect()
            ->route('admin.media.index')
            ->with('success', $config->label.' created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $config = $this->config();
        $record = $config->model::query()->findOrFail($id);

        $data = collect($this->validatePayload($request, $config))->only([
            'title', 'caption', 'video_url', 'thumbnail_path', 'status', 'associated_type', 'associated_id',
        ])->all();

        $record->update($data);
        $this->storeFiles($request, $config, $record);

        return redirect()
            ->route('admin.media.index')
            ->with('success', $config->label.' updated.');
    }
}
