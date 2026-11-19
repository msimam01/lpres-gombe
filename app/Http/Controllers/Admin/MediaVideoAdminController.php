<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\MediaItem;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Dedicated "Add Video" workflow. Videos are hosted externally on YouTube or
 * Facebook: the form validates the pasted link, never accepts arbitrary embed
 * HTML, and stores the canonical watch URL. Any embed URL is generated only
 * in trusted helpers at render time (server or the public page's strict
 * parser) — never stored from user input.
 *
 * Facebook video links carry no public thumbnail our server can derive, so a
 * cover image is required for them: the public page shows that cover as the
 * video and, on click, opens the video on Facebook where it plays.
 */
class MediaVideoAdminController extends MediaModuleController
{
    private const YOUTUBE_PATTERN = '#^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,63})(?:[?&/].*)?$#';

    private const FACEBOOK_PATTERN = '#^(?:https?://)?(?:www\.|m\.|web\.)?(?:facebook\.com/(?:watch/?\?v=\d+|[\w.\-]+/videos/(?:[\w.\-]+/)?\d+|reel/\d+|share/[a-z]/[\w\-]+|video\.php\?v=\d+)|fb\.watch/[\w\-]+)#i';

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
                ['key' => 'video_url', 'label' => 'Video link (YouTube or Facebook)', 'type' => 'text', 'required' => true],
                [
                    'key' => 'thumbnail_path',
                    'label' => 'Cover image / thumbnail (required for Facebook videos; YouTube thumbnail used when empty)',
                    'type' => 'image',
                    'removable' => true,
                ],
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
                    if (! preg_match(self::YOUTUBE_PATTERN, (string) $value)
                        && ! preg_match(self::FACEBOOK_PATTERN, (string) $value)) {
                        $fail('The video link must be a YouTube link (youtube.com/watch, youtu.be) or a Facebook video link (facebook.com/watch, facebook.com/…/videos, facebook.com/reel or fb.watch).');
                    }
                },
            ],
        ])['video_url'] ?? null;

        // Store a canonical URL; identifiers are extracted from this format
        // and never from raw markup supplied by the user.
        if ($data['video_url'] !== null) {
            $data['video_url'] = self::canonicalVideoUrl($data['video_url']);
        }

        $data['media_type'] = MediaItem::TYPE_VIDEO;

        // Facebook thumbnails cannot be derived server-side, so the card needs
        // an uploaded cover — either fresh in this request or already stored.
        if (MediaItem::videoProvider($data['video_url']) === 'facebook'
            && ! $request->hasFile('thumbnail_path')
            && blank($ignoreId ? MediaItem::query()->find($ignoreId)?->thumbnail_path : null)) {
            throw ValidationException::withMessages([
                'thumbnail_path' => 'A cover image is required for Facebook videos so the public page has a thumbnail to show.',
            ]);
        }

        return $data;
    }

    /**
     * YouTube links collapse to the canonical watch URL; Facebook links are
     * kept as supplied (their share identifiers are opaque) with a scheme
     * added when the admin pasted a bare link.
     */
    private static function canonicalVideoUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match(self::YOUTUBE_PATTERN, $url, $matches)) {
            return 'https://www.youtube.com/watch?v='.$matches[1];
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
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

        // The record id lets validation see the cover image already stored,
        // so editing a Facebook video without re-uploading still passes.
        $data = collect($this->validatePayload($request, $config, $id))->only([
            'title', 'caption', 'video_url', 'thumbnail_path', 'status', 'associated_type', 'associated_id',
        ])->all();

        $record->update($data);
        $this->storeFiles($request, $config, $record);

        return redirect()
            ->route('admin.media.index')
            ->with('success', $config->label.' updated.');
    }
}
