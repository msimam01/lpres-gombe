<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\ProjectComponent;

class ProjectComponentAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: ProjectComponent::class,
            routeBase: 'project-components',
            label: 'Project component',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'image_path', 'label' => 'Image', 'type' => 'image'],
                ['key' => 'display_order', 'label' => 'Display order', 'type' => 'integer', 'default' => 0],
                ['key' => 'status', 'label' => 'Published', 'type' => 'boolean'],
            ],
        );
    }
}
