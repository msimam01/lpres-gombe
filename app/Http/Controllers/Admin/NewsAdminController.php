<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: News::class,
            routeBase: 'news',
            label: 'News article',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
                ['key' => 'featured_image_path', 'label' => 'Featured image', 'type' => 'image'],
                // Images embedded in the article body (imported from WordPress).
                ['key' => 'gallery_image_paths', 'label' => 'Article images', 'type' => 'gallery'],
                ['key' => 'published_at', 'label' => 'Publication date', 'type' => 'date'],
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
     * The excerpt is fully derived from the title (product requirement), so
     * it is never a form field: it is re-computed from the current title on
     * every create/update. Requests bypassing the UI stay consistent because
     * any supplied excerpt value is discarded here.
     */
    protected function validatePayload(Request $request, ModuleConfig $config, ?int $ignoreId = null): array
    {
        $data = parent::validatePayload($request, $config, $ignoreId);

        if (array_key_exists('title', $data)) {
            $data['excerpt'] = $this->excerptFromTitle($data['title']);
        }

        return $data;
    }

    private function excerptFromTitle(string $title): string
    {
        return Str::limit(trim($title), 250, '');
    }
}
