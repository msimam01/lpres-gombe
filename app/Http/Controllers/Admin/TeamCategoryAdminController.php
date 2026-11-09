<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\TeamCategory;
use Illuminate\Http\RedirectResponse;

class TeamCategoryAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: TeamCategory::class,
            routeBase: 'team-categories',
            label: 'Team category',
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
        $category = TeamCategory::query()->findOrFail($id);

        // Deletion is blocked while members are assigned to the category;
        // staff must reassign those members first, so no relationship is
        // ever silently discarded.
        $assigned = $category->members()->count();
        if ($assigned > 0) {
            return redirect()
                ->back()
                ->with('error', $assigned.' team member(s) still use this category. Reassign them before deleting it.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Team category deleted.');
    }
}
