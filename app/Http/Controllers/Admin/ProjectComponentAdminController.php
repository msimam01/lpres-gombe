<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\ProjectComponent;
use Illuminate\Http\RedirectResponse;

class ProjectComponentAdminController extends AdminModuleController
{
    /**
     * Delete the component and then release its featured image. The release
     * happens after the row is gone so the shared-file guard does not find
     * this record's own reference, and it never removes a file another
     * content record still uses.
     */
    public function destroy(int $id): RedirectResponse
    {
        $component = ProjectComponent::query()->findOrFail($id);
        $imagePath = $component->image_path;

        $component->delete();

        if (filled($imagePath)) {
            $this->releaseFile($imagePath);
        }

        return redirect()->back()->with('success', 'Project component deleted.');
    }

    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: ProjectComponent::class,
            routeBase: 'project-components',
            label: 'Project component',
            // Slugs are generated from the title on create and pinned on
            // update, so public component URLs stay bookmarkable.
            titleFrom: 'title',
            fields: [
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'Shown as the card heading on the homepage and as the page title.',
                ],
                [
                    'key' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'help' => 'A short excerpt appears on the homepage card; the full text appears on the component page.',
                ],
                [
                    'key' => 'image_path',
                    'label' => 'Featured image',
                    'type' => 'image',
                    'removable' => true,
                    'help' => 'JPG, PNG or WebP up to 4 MB. The whole image is shown, never cropped.',
                ],
                [
                    'key' => 'display_order',
                    'label' => 'Display order',
                    'type' => 'integer',
                    'default' => 0,
                    'help' => 'Lower numbers appear first on the homepage. Ties fall back to creation order.',
                ],
                ['key' => 'status', 'label' => 'Published', 'type' => 'boolean'],
            ],
        );
    }
}
