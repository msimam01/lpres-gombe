import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Search } from 'lucide-react';
import { toastError, toastSuccess } from '@/lib/toast';

export interface AdminField {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'select' | 'boolean' | 'integer' | 'date' | 'image' | 'document' | 'video' | 'beneficiary-table' | 'gallery';
    required?: boolean;
    options?: Record<string, string>;
    default?: string;
    /** Optional link rendered under a select, e.g. "Manage categories". */
    manageHref?: string;
    manageLabel?: string;
    /** Image fields: show a "remove current image" action in the form. */
    removable?: boolean;
    /** Extra helper line rendered under the field label. */
    help?: string;
}

export interface AdminModule {
    routeBase: string;
    label: string;
    labelFrom: string | null;
    titleFrom: string | null;
    /** Modules without a status control (e.g. categories) hide the visibility column/actions. */
    hasStatus?: boolean;
    fields: AdminField[];
}

export interface AdminRow {
    id: number;
    label: string;
    slug: string | null;
    status: string | boolean;
    updated_at: string | null;
}

interface ResourceIndexProps {
    module: AdminModule;
    rows: {
        data: AdminRow[];
        current_page: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

function statusBadge(status: string | boolean) {
    if (typeof status === 'boolean') {
        return status ? (
            <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Published</span>
        ) : (
            <span className="rounded-full bg-neutral-200 px-2.5 py-0.5 text-xs font-semibold text-neutral-700">Hidden</span>
        );
    }

    return status === 'published' ? (
        <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Published</span>
    ) : (
        <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Draft</span>
    );
}

export default function ResourceIndex({ module, rows }: ResourceIndexProps) {
    const page = usePage();
    const successMessage = page.props.success as string | undefined;
    const [search, setSearch] = useState('');
    const visible = rows.data.filter((row) => row.label.toLowerCase().includes(search.toLowerCase()));

    // Toast feedback for server-confirmed actions (the flash survives the
    // redirect), so records are created/updated/deleted with one shared
    // notification pattern.
    useEffect(() => {
        if (successMessage) {
            toastSuccess(successMessage);
        }
    }, [successMessage]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: module.label, href: `/admin/${module.routeBase}` },
    ];

    const routeName = module.routeBase.replace(/\//g, '.');

    const toggle = (id: number) => {
        router.post(route(`admin.${routeName}.toggle`, id), {}, {
            preserveScroll: true,
            onSuccess: () => toastSuccess(`${module.label} visibility updated.`),
            onError: () => toastError(`${module.label} could not be updated.`),
        });
    };

    const destroy = (row: AdminRow) => {
        if (window.confirm(`Delete "${row.label}"? This cannot be undone.`)) {
            router.delete(route(`admin.${routeName}.destroy`, row.id), {
                preserveScroll: true,
                onSuccess: () => toastSuccess(`${module.label} deleted.`),
                onError: () => toastError(`${module.label} could not be deleted.`),
            });
        }
    };

    const showStatus = module.hasStatus ?? true;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={module.label} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-extrabold text-[#223645]">{module.label}</h1>
                        {showStatus && (
                            <span className="text-lpres-green rounded-full bg-lpres-green/10 px-3 py-0.5 text-xs font-bold tracking-wide uppercase">
                                Public content
                            </span>
                        )}
                        <Link
                            href={route(`admin.${routeName}.create`)}
                            className="bg-lpres-green hover:bg-lpres-deep ml-auto inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white transition-colors"
                        >
                            New {module.label.toLowerCase()}
                        </Link>
                    </div>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <div className="relative">
                            <Search aria-hidden="true" className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-neutral-400" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={`Filter ${module.label.toLowerCase()} by title…`}
                                aria-label={`Filter records by title`}
                                className="h-10 w-72 rounded-md border border-input bg-background pl-8 text-sm placeholder:text-neutral-400"
                            />
                        </div>
                        {search && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="text-sm text-neutral-500 hover:text-neutral-800 hover:underline"
                            >
                                Clear
                            </button>
                        )}
                        <span className="text-sm text-neutral-500">
                            {visible.length} of {rows.data.length} shown
                        </span>
                    </div>
                </header>

                <div className="border-border/70 overflow-hidden rounded-xl border bg-white">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b bg-[#f3f6f7] text-xs tracking-wider text-neutral-500 uppercase">
                                <th className="px-4 py-3 font-semibold">Record</th>
                                {showStatus && <th className="px-4 py-3 font-semibold">Visibility</th>}
                                <th className="hidden px-4 py-3 font-semibold md:table-cell">Updated</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((row) => (
                                <tr key={row.id} className="border-border/60 border-b transition-colors last:border-0 hover:bg-[#f7fafb]">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={route(`admin.${routeName}.edit`, row.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {row.label}
                                        </Link>
                                        {row.slug && <span className="block text-xs text-neutral-500">/{row.slug}</span>}
                                    </td>
                                    {showStatus && <td className="px-4 py-3">{statusBadge(row.status)}</td>}
                                    <td className="hidden px-4 py-3 text-neutral-500 md:table-cell">{row.updated_at}</td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        <Link
                                            href={route(`admin.${routeName}.edit`, row.id)}
                                            className="mr-2 font-semibold text-lpres-deep hover:text-lpres-green hover:underline"
                                        >
                                            Edit
                                        </Link>
                                        {showStatus && (
                                            <button
                                                onClick={() => toggle(row.id)}
                                                className="mr-2 font-medium text-blue-600 hover:underline"
                                            >
                                                {row.status === 'published' || row.status === true ? 'Unpublish' : 'Publish'}
                                            </button>
                                        )}
                                        <button onClick={() => destroy(row)} className="font-medium text-red-600 hover:underline">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {visible.length === 0 && (
                                <tr>
                                    <td colSpan={showStatus ? 4 : 3} className="px-4 py-14 text-center">
                                        {rows.data.length === 0 ? (
                                            <div className="mx-auto max-w-sm">
                                                <p className="text-lg font-semibold text-[#223645]">Nothing here yet</p>
                                                <p className="mt-1 text-sm text-neutral-500">
                                                    No {module.label.toLowerCase()} records exist. Create the first one to see it on the
                                                    public site right away.
                                                </p>
                                                <Link
                                                    href={route(`admin.${routeName}.create`)}
                                                    className="bg-lpres-green hover:bg-lpres-deep mt-4 inline-flex rounded-md px-4 py-2 text-sm font-semibold text-white transition-colors"
                                                >
                                                    New {module.label.toLowerCase()}
                                                </Link>
                                            </div>
                                        ) : (
                                            <span className="text-neutral-500">No records match the filter.</span>
                                        )}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {rows.last_page > 1 && (
                    <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                        {rows.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-lpres-green text-white' : 'hover:bg-neutral-100'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded border px-3 py-1 text-sm text-neutral-400"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </nav>
                )}

                {successMessage && (
                    <p
                        role="status"
                        className="rounded-lg border border-lpres-green/30 bg-lpres-green/10 px-4 py-2.5 text-sm font-semibold text-lpres-deep"
                    >
                        {successMessage}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
