import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavGroup, type NavItem } from '@/types';
import { ChevronRight } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';

const isActiveRoute = (item: NavItem): boolean => item.isActive ?? item.url === window.location.pathname;

/**
 * Admin sidebar navigation. A labelled group with multiple items renders as
 * an expandable menu (e.g. "Projects", "Project Team"); label-less or single
 * item groups render as a standard flat link. A group whose child route is
 * active opens automatically so the active link stays visible.
 */
export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    // Subscribe to route changes so active states/groups collapse states update on navigation.
    usePage();

    return (
        <>
            {groups.map((group) => {
                const items = group.items;
                const anyActive = items.some(isActiveRoute);

                if (!group.title || items.length <= 1) {
                    return (
                        <SidebarGroup key={group.title ?? items[0]?.title} className="px-2 py-0">
                            <SidebarMenu className="pt-1">
                                {items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton asChild isActive={isActiveRoute(item)}>
                                            <Link href={item.url} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                }

                return (
                    <Collapsible key={group.title} defaultOpen={anyActive} className="group/collapsible">
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel asChild className="text-xs font-medium tracking-normal uppercase">
                                <CollapsibleTrigger className="flex w-full items-center gap-1"
                                    aria-label={`Toggle ${group.title} menu`}>
                                    <span>{group.title}</span>
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                    />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarMenuSub data-active={anyActive ? 'true' : undefined}>
                                    {items.map((item) => (
                                        <SidebarMenuSubItem key={item.title}>
                                            <SidebarMenuSubButton asChild isActive={isActiveRoute(item)}>
                                                <Link href={item.url} prefetch>
                                                    {item.title}
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    ))}
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </>
    );
}
