<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\VisualReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VisualReportAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: VisualReport::class,
            routeBase: 'visual-reports',
            label: 'Visual report',
            titleFrom: 'title',
            fields: [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'image_path', 'label' => 'Report image', 'type' => 'image', 'removable' => true],
                ['key' => 'file_path', 'label' => 'Report file (optional PDF/Office)', 'type' => 'document'],
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
     * A visual report IS an image: require an image when creating, or an
     * uploaded replacement / retained path when updating (and not removing).
     * Plain closures in rules arrays are not enforced by this validator
     * version, so the requirement is checked explicitly and thrown as a
     * ValidationException the form can render next to the field.
     */
    protected function validatePayload(Request $request, ModuleConfig $config, ?int $ignoreId = null): array
    {
        $data = parent::validatePayload($request, $config, $ignoreId);

        $hasUpload = $request->hasFile('image_path');
        $removing = (bool) $request->boolean('remove_image_path');
        $retained = ! $hasUpload && ! $removing && filled($request->input('image_path')); // update: form re-posts the stored path

        if (! $hasUpload && ! $retained) {
            throw ValidationException::withMessages([
                'image_path' => ['A visual report requires an image.'],
            ]);
        }

        return $data;
    }
}
