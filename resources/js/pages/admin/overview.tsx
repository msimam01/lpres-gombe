import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    FileBarChart,
    Folder,
    Images,
    Mail,
    Newspaper,
    Settings,
    Sigma,
    Tags,
    UsersRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface CountRow {
    module: string;
    count: number;
    published: number;
    route: string;
}

/** Icons matched to each managed module by its admin route. */
const MODULE_ICONS: Record<string, LucideIcon> = {
    '/admin/news': Newspaper,
    '/admin/events': CalendarDays,
    '/admin/projects': Building2,
    '/admin/project-categories': Tags,
    '/admin/team': UsersRound,
    '/admin/team-categories': Tags,
    '/admin/project-components': Sigma,
    '/admin/clusters': Folder,
    '/admin/media': Images,
    '/admin/visual-reports': FileBarChart,
    '/admin/enquiries': Mail,
    '/admin/settings': Settings,
};

export default function Overview({ counts }: { counts: CountRow[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content overview" />
            <div className="flex h-full flex-1 flex-col gap-5 p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">Content overview</h1>
                    <p className="mt-1 text-sm text-[#838e91]">
                        Everything on the public website is managed here — the public pages read this data live.
                    </p>
                </header>

                {counts.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center text-[#838e91]">
                        No content modules are configured yet.
                    </div>
                ) : (
                    <ul className="grid auto-rows-min gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {counts.map((row) => {
                            const Icon = MODULE_ICONS[row.route] ?? Folder;
                            return (
                                <li key={row.module}>
                                    <Link
                                        href={row.route}
                                        className="group border-border/70 relative block h-full overflow-hidden rounded-xl border bg-white p-5 shadow-sm transition-[transform,box-shadow,border-color] duration-300 hover:-translate-y-0.5 hover:border-lpres-green/40 hover:shadow-[0_16px_32px_-18px_rgba(0,59,73,0.35)]"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="bg-lpres-green absolute inset-x-0 top-0 h-1 scale-x-0 transition-transform duration-300 group-hover:scale-x-100"
                                        />
                                        <div className="flex items-start justify-between gap-3">
                                            <span className="text-base font-semibold text-[#223645] group-hover:text-lpres-deep">{row.module}</span>
                                            <span className="border-border/70 grid size-10 shrink-0 place-items-center rounded-lg border bg-[#f3f6f7] text-lpres-deep transition-colors duration-300 group-hover:bg-lpres-green/10 group-hover:text-lpres-green">
                                                <Icon aria-hidden="true" className="size-5" />
                                            </span>
                                        </div>
                                        <p className="mt-4 text-3xl font-extrabold text-[#223645]">{row.count}</p>
                                        <p className="mt-1 text-sm text-[#838e91]">
                                            {row.published} published · <span className="text-lpres-green font-semibold group-hover:underline">Manage</span>
                                        </p>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
