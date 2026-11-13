<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\Project;
use App\Models\ProjectCategory;

class ProjectAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        $lgas = config('lpres.lgas');

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

                // --- Location -------------------------------------------------
                [
                    'key' => 'lga',
                    'label' => 'Local Government Area',
                    'type' => 'select',
                    'options' => array_combine($lgas, $lgas),
                    'section' => 'location',
                    'help' => 'Select the approved LGA the project is located in. It is never inferred from the title or coordinates.',
                ],
                [
                    'key' => 'ward',
                    'label' => 'Ward',
                    'type' => 'text',
                    'max' => 96,
                    'section' => 'location',
                    'help' => 'Ward name as used by the project. Free text until a verified ward list is approved.',
                ],
                [
                    'key' => 'address',
                    'label' => 'Location description',
                    'type' => 'text',
                    'section' => 'location',
                    'help' => 'Optional landmark or address that helps someone find the project. This is not the LGA or ward.',
                ],
                [
                    'key' => 'location_visibility',
                    'label' => 'Public map visibility',
                    'type' => 'select',
                    'options' => config('lpres.location_visibilities'),
                    'default' => config('lpres.location_visibility_default'),
                    'allowEmpty' => false,
                    'section' => 'location',
                    'help' => 'Controls what a future public map may show. Exact coordinates are never published for restricted projects.',
                ],
                [
                    'key' => 'location',
                    'label' => 'Map position',
                    'type' => 'location',
                    'section' => 'location',
                    'help' => 'Click the map or use the device location button, then drag the pin to refine it.',
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
            // Edited by the composite map picker, validated below.
            extraKeys: ['latitude', 'longitude'],
        );
    }

    /**
     * Coordinates are optional, but they only make sense as a pair and must
     * be real decimal degrees. No invented bounding box is applied.
     */
    protected function additionalValidationRules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
