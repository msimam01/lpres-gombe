<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\TeamCategory;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: TeamMember::class,
            routeBase: 'team',
            label: 'Team member',
            titleFrom: 'name',
            labelFrom: 'name',
            fields: [
                ['key' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true],
                [
                    'key' => 'team_category_id',
                    'label' => 'Team category',
                    'type' => 'select',
                    'options' => TeamCategory::query()->active()->pluck('name', 'id')->all(),
                    'manageHref' => route('admin.team-categories.index'),
                    'manageLabel' => 'Manage team categories',
                ],
                ['key' => 'photograph_path', 'label' => 'Photograph', 'type' => 'image'],
                ['key' => 'designation', 'label' => 'Designation / job title', 'type' => 'text', 'required' => true],
            ],
        );
    }

    /**
     * Ordinary members are created visible immediately: the form only asks
     * for photograph, name, designation and category, while the list keeps
     * the publish/unpublish action for exceptional hiding.
     */
    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();

        $data = $this->validatePayload($request, $config);
        $data = $this->applySlug($config, $data);
        $data['status'] = true;

        $member = TeamMember::create($data);
        $this->storeFiles($request, $config, $member);

        return redirect()
            ->route('admin.team.index')
            ->with('success', $config->label.' created.');
    }
}
