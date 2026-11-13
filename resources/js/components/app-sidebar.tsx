import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    FileBarChart,
    FileText,
    Folder,
    GalleryHorizontal,
    GalleryHorizontalEnd,
    ImagePlus,
    Images,
    LayoutGrid,
    Mail,
    Newspaper,
    Settings,
    Sigma,
    Tags,
    UserPlus,
    Users,
    Video,
} from 'lucide-react';

import AppLogo from './app-logo';

const mainNavGroups: (NavGroup | NavItem)[] = (() => {
    const flat = (item: NavItem) => ({ items: [item] }) as NavGroup;

    const projects: NavGroup = {
        title: 'Projects',
        items: [
            { title: 'All Projects', url: '/admin/projects', icon: Building2 },
            { title: 'Add Project', url: '/admin/projects/create', icon: UserPlus },
            { title: 'Project Categories', url: '/admin/project-categories', icon: Tags },
        ],
    };

    const team: NavGroup = {
        title: 'Project Team',
        items: [
            { title: 'All Team Members', url: '/admin/team', icon: Users },
            { title: 'Add Team Member', url: '/admin/team/create', icon: UserPlus },
            { title: 'Team Categories', url: '/admin/team-categories', icon: Tags },
        ],
    };

    const media: NavGroup = {
        title: 'Media',
        items: [
            { title: 'All Media', url: '/admin/media', icon: Images },
            { title: 'Add Image', url: '/admin/media/images/create', icon: ImagePlus },
            { title: 'Add Video', url: '/admin/media/videos/create', icon: Video },
            { title: 'Galleries', url: '/admin/media/galleries', icon: GalleryHorizontal },
            { title: 'Add Gallery', url: '/admin/media/galleries/create', icon: GalleryHorizontalEnd },
        ],
    };

    return [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'News', url: '/admin/news', icon: Newspaper },
        { title: 'Events', url: '/admin/events', icon: CalendarDays },
        { title: 'Project Components', url: '/admin/project-components', icon: Sigma },
        { title: 'Documents', url: '/admin/documents', icon: FileText },
        projects,
        team,
        { title: 'Value chain clusters', url: '/admin/clusters', icon: Folder },
        { title: 'Visual reports', url: '/admin/visual-reports', icon: FileBarChart },
        media,
        // { title: 'Contact enquiries', url: '/admin/enquiries', icon: Mail },
        { title: 'Settings', url: '/admin/settings', icon: Settings },
    ].map((entry) => ('url' in entry ? flat(entry) : entry));
})();

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={mainNavGroups as NavGroup[]} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
