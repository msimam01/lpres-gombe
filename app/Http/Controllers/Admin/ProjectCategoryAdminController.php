<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\ProjectCategory;
use Illuminate\Http\RedirectResponse;

class ProjectCategoryAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: ProjectCategory::class,
            routeBase: 'project-categories',
            label: 'Project category',
            titleFrom: 'name',
            labelFrom: 'name',
            fields: [
                ['key' => 'name', 'label' => 'Category name', 'type' => 'text', 'required' => true],
                ['key' => 'description', 'label' => 'Description (optional)', 'type' => 'textarea'],
            ],
        );
    }

    public function destroy(int $id): RedirectResponse
    {
        $category = ProjectCategory::query()->findOrFail($id);

        // Deletion is blocked while projects still reference the category;
        // staff must reassign those projects first, so no relationship is
        // ever silently discarded.
        $assigned = $category->projects()->count();
        if ($assigned > 0) {
            return redirect()
                ->back()
                ->with('error', $assigned.' project(s) still use this category. Reassign them before deleting it.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Project category deleted.');
    }
}
