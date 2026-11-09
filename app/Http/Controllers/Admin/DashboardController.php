<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactEnquiry;
use App\Models\Event;
use App\Models\MediaItem;
use App\Models\News;
use App\Models\Project;
use App\Models\ProjectComponent;
use App\Models\TeamMember;
use App\Models\ValueChainCluster;
use App\Models\VisualReport;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/overview', [
            'counts' => [
                ['module' => 'News', 'count' => News::count(), 'published' => News::query()->where('status', 'published')->count(), 'route' => route('admin.news.index')],
                ['module' => 'Events', 'count' => Event::count(), 'published' => Event::query()->where('status', 'published')->count(), 'route' => route('admin.events.index')],
                ['module' => 'Projects', 'count' => Project::count(), 'published' => Project::query()->where('status', 'published')->count(), 'route' => route('admin.projects.index')],
                ['module' => 'Project components', 'count' => ProjectComponent::count(), 'published' => ProjectComponent::published()->count(), 'route' => route('admin.project-components.index')],
                ['module' => 'Team members', 'count' => TeamMember::count(), 'published' => TeamMember::published()->count(), 'route' => route('admin.team.index')],
                ['module' => 'Value chain clusters', 'count' => ValueChainCluster::count(), 'published' => ValueChainCluster::published()->count(), 'route' => route('admin.clusters.index')],
                ['module' => 'Media', 'count' => MediaItem::count(), 'published' => MediaItem::published()->count(), 'route' => route('admin.media.index')],
                ['module' => 'Visual reports', 'count' => VisualReport::count(), 'published' => VisualReport::published()->count(), 'route' => route('admin.visual-reports.index')],
                ['module' => 'Contact enquiries', 'count' => ContactEnquiry::count(), 'published' => ContactEnquiry::unhandled()->count(), 'route' => route('admin.enquiries.index')],
            ],
        ]);
    }
}
