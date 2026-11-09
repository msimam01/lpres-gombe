<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Event;

class EventAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: Event::class,
            routeBase: 'events',
            label: 'Event',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                ['key' => 'image_path', 'label' => 'Image', 'type' => 'image'],
                ['key' => 'gallery_image_paths', 'label' => 'Event gallery', 'type' => 'gallery'],
                ['key' => 'starts_at', 'label' => 'Starts on', 'type' => 'date'],
                ['key' => 'ends_at', 'label' => 'Ends on', 'type' => 'date'],
                ['key' => 'location', 'label' => 'Location', 'type' => 'text'],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['draft' => 'Draft', 'published' => 'Published'],
                ],
            ],
        );
    }
}
