<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\ProjectComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => 'text',
                    'required' => true,
                    'help' => 'Shown as the card heading on the homepage and as the page title.',
                ],
                [
                    'key' => 'slug',
                    'label' => 'Slug',
                    'type' => 'text',
                    'help' => 'Leave blank to generate one from the title. Used in the public component URL.',
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
                    'help' => 'JPG, PNG or WebP up to 4 MB. The card frame is filled; the detail page shows the whole image.',
                ],
                [
                    'key' => 'display_order',
                    'label' => 'Sort order',
                    'type' => 'integer',
                    'default' => 0,
                    'help' => 'Lower numbers appear first on the homepage. Ties fall back to creation order.',
                ],
                ['key' => 'status', 'label' => 'Published', 'type' => 'boolean'],
            ],
        );
    }

    /** Slugs are unique and URL-safe; blank means "derive from the title". */
    protected function validationRules(ModuleConfig $config, ?int $ignoreId = null): array
    {
        return array_merge(parent::validationRules($config, $ignoreId), [
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('project_components', 'slug')->ignore($ignoreId),
            ],
        ]);
    }

    protected function applySlug(ModuleConfig $config, array $data): array
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title'] ?? '');

        return $data;
    }

    /** Administrators may adjust the slug; uniqueness is still enforced. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $component = ProjectComponent::query()->findOrFail($id);
        $data = $this->validatePayload($request, $this->config(), $id);

        // Only an explicitly supplied slug changes the URL; renaming a record
        // otherwise keeps its existing public link stable.
        $requested = trim((string) ($data['slug'] ?? ''));
        $data['slug'] = $requested !== ''
            ? $this->uniqueSlug($requested, $data['title'] ?? $component->title, $component->id)
            : ($component->slug ?: $this->uniqueSlug(null, $data['title'] ?? $component->title, $component->id));

        $component->update($data);
        $this->storeFiles($request, $this->config(), $component);

        return redirect()
            ->route('admin.project-components.index')
            ->with('success', 'Project component updated.');
    }

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

    private function uniqueSlug(?string $requested, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim((string) $requested));

        if ($base === '') {
            $base = Str::slug(Str::limit($title, 120, '')) ?: 'component-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $attempt = 2;

        while (
            ProjectComponent::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$attempt++;
        }

        return $slug;
    }
}
