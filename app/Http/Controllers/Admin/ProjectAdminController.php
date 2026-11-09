<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Project;
use App\Models\ProjectCategory;

class ProjectAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: Project::class,
            routeBase: 'projects',
            label: 'Project',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                [
                    'key' => 'project_category_id',
                    'label' => 'Category',
                    'type' => 'select',
                    'options' => ProjectCategory::query()->active()->pluck('name', 'id')->all(),
                    'manageHref' => route('admin.project-categories.index'),
                    'manageLabel' => 'Manage categories',
                ],
                ['key' => 'featured_image_path', 'label' => 'Featured image', 'type' => 'image'],
                ['key' => 'gallery_image_paths', 'label' => 'Project gallery', 'type' => 'gallery'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
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
