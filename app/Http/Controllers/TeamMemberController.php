<?php

namespace App\Http\Controllers;

use App\Models\TeamMember;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    /**
     * Project Specialists only. The State Project Coordinator (SPC) is NOT a
     * member record: the SPC's introduction lives on this page's dedicated
     * section and the full profile is the hardcoded editorial page at
     * /spc-profile, so ordinary members never get a Read More link or an
     * individual profile page.
     */
    public function index(): Response
    {
        return Inertia::render('project-team', [
            'members' => TeamMember::published()
                ->with('teamCategory:id,name,slug')
                ->get(['id', 'name', 'photograph_path', 'designation', 'team_category_id'])
                ->map(fn (TeamMember $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'image' => $member->photograph_path ? asset('storage/'.$member->photograph_path) : null,
                    'designation' => $member->designation,
                    'category' => $member->teamCategory?->name,
                ])
                ->all(),
        ]);
    }
}
