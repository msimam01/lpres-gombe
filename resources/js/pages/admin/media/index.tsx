import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Image, List, Video } from 'lucide-react';
import { actionSuccess, toastError } from '@/lib/toast';

interface MediaModuleRow {
    id: number;
    label: string;
    status: string;
    type: string;
    editHref: string;
}

interface GalleryRow {
    id: number;
    label: string;
    status: string;
    images: number;
    editHref: string;
}

interface MediaIndexProps {
    media: MediaModuleRow[];
    mediaLinks: { url: string | null; label: string; active: boolean }[];
    mediaLastPage: number;
    galleries: GalleryRow[];
    galleryLinks: { url: string | null; label: string; active: boolean }[];
    galleryLastPage: number;
}

const ADD_MEDIA = [
    { label: 'Add image', href: '/admin/media/images/create' },
    { label: 'Add video', href: '/admin/media/videos/create' },
    { label: 'Add gallery', href: '/admin/media/galleries/create' },
];

function statusBadge(status: string) {
    return status === 'published' ? (
        <span className="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Published</span>
    ) : (
        <span className="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Draft</span>
    );
}

function Paginator({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="flex flex-wrap gap-1">
            {links.map(
                (link, index) =>
                    link.url && (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={`rounded border px-3 py-1 text-sm ${
                                link.active ? 'bg-lpres-green text-white' : 'hover:bg-neutral-100'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
            )}
        </nav>
    );
}

/**
 * "All Media" — the combined media index: video/image item records plus
 * galleries, each linking into its dedicated module workflow.
 */
export default function MediaIndex({ media, mediaLinks, mediaLastPage, galleries, galleryLinks, galleryLastPage }: MediaIndexProps) {
    const toggleItem = (row: MediaModuleRow) => {
        router.post(
            route(`admin.media.${row.type === 'video' ? 'videos' : 'images'}.toggle`, row.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => actionSuccess('Media item visibility updated.'),
                onError: () => toastError('Media item could not be updated.'),
            },
        );
    };

    const toggleGallery = (row: GalleryRow) => {
        router.post(
            route('admin.media.galleries.toggle', row.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => actionSuccess('Gallery visibility updated.'),
                onError: () => toastError('Gallery could not be updated.'),
            },
        );
    };

    const deleteGallery = (row: GalleryRow) => {
        if (window.confirm(`Delete gallery "${row.label}"? This cannot be undone.`)) {
            router.delete(route('admin.media.galleries.destroy', row.id), {
                preserveScroll: true,
                onSuccess: () => actionSuccess('Gallery deleted.'),
                onError: () => toastError('Gallery could not be deleted.'),
            });
        }
    };

    const deleteItem = (row: MediaModuleRow) => {
        if (window.confirm(`Delete "${row.label}"? This cannot be undone.`)) {
            router.delete(
                route(`admin.media.${row.type === 'video' ? 'videos' : 'images'}.destroy`, row.id),
                {
                    preserveScroll: true,
                    onSuccess: () => actionSuccess('Media item deleted.'),
                    onError: () => toastError('Media item could not be deleted.'),
                },
            );
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Media', href: '/admin/media' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Media" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <header className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-extrabold text-[#223645]">Media</h1>
                    <span className="text-lpres-green rounded-full bg-lpres-green/10 px-3 py-0.5 text-xs font-bold tracking-wide uppercase">
                        Public content
                    </span>
                    <div className="ml-auto flex flex-wrap gap-2">
                        {ADD_MEDIA.map((entry) => (
                            <Link
                                key={entry.href}
                                href={entry.href}
                                className="bg-lpres-green hover:bg-lpres-deep inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white transition-colors"
                            >
                                {entry.label}
                            </Link>
                        ))}
                    </div>
                </header>

                <section aria-labelledby="media-items-heading" className="border-border/70 overflow-hidden rounded-xl border bg-white">
                    <header className="flex items-center gap-3 border-b bg-[#f3f6f7] px-4 py-3">
                        <List aria-hidden="true" className="size-4 text-neutral-500" />
                        <h2 id="media-items-heading" className="text-sm font-semibold">
                            Standalone media items
                        </h2>
                        <span className="text-xs text-neutral-500">{media.length} on this page</span>
                    </header>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border/60 text-xs tracking-wider text-neutral-500 uppercase">
                                <th className="px-4 py-3 font-semibold">Item</th>
                                <th className="px-4 py-3 font-semibold">Type</th>
                                <th className="px-4 py-3 font-semibold">Visibility</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {media.map((row) => (
                                <tr key={`${row.type}-${row.id}`} className="border-border/60 border-b last:border-b-0 hover:bg-[#f7fafb]">
                                    <td className="px-4 py-3">
                                        <span className="inline-flex items-center gap-2">
                                            {row.type === 'video' ? (
                                                <Video aria-hidden="true" className="size-4 text-neutral-400" />
                                            ) : (
                                                <Image aria-hidden="true" className="size-4 text-neutral-400" />
                                            )}
                                            <Link href={row.editHref} className="font-medium hover:underline">
                                                {row.label}
                                            </Link>
                                        </span>
                                    </td>
                                    <td className="text-lpres-deep px-4 py-3 capitalize">{row.type}</td>
                                    <td className="px-4 py-3">{statusBadge(row.status)}</td>
                                    <td className="text-right whitespace-nowrap">
                                        <button onClick={() => toggleItem(row)} className="mr-2 font-medium text-blue-600 hover:underline">
                                            {row.status === 'published' ? 'Unpublish' : 'Publish'}
                                        </button>
                                        <button onClick={() => deleteItem(row)} className="font-medium text-red-600 hover:underline">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {media.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-10 text-center text-neutral-500">
                                        No media items yet — use the Add image / Add video buttons above.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    {mediaLastPage > 1 && <Paginator links={mediaLinks} />}
                </section>

                <section aria-labelledby="galleries-heading" className="border-border/70 overflow-hidden rounded-xl border bg-white">
                    <header className="flex items-center gap-3 border-b bg-[#f3f6f7] px-4 py-3">
                        <Image aria-hidden="true" className="size-4 text-neutral-500" />
                        <h2 id="galleries-heading" className="text-sm font-semibold">
                            Galleries
                        </h2>
                        <span className="text-xs text-neutral-500">{galleries.length} on this page</span>
                    </header>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-border/60 text-xs tracking-wider text-neutral-500 uppercase">
                                <th className="px-4 py-3 font-semibold">Gallery</th>
                                <th className="px-4 py-3 font-semibold">Images</th>
                                <th className="px-4 py-3 font-semibold">Visibility</th>
                                <th className="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {galleries.map((row) => (
                                <tr key={row.id} className="border-border/60 border-b last:border-b-0 hover:bg-[#f7fafb]">
                                    <td className="px-4 py-3">
                                        <Link href={row.editHref} className="font-medium hover:underline">
                                            {row.label}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-neutral-600">{row.images}</td>
                                    <td className="px-4 py-3">{statusBadge(row.status)}</td>
                                    <td className="text-right whitespace-nowrap">
                                        <button onClick={() => toggleGallery(row)} className="mr-2 font-medium text-blue-600 hover:underline">
                                            {row.status === 'published' ? 'Unpublish' : 'Publish'}
                                        </button>
                                        <button onClick={() => deleteGallery(row)} className="font-medium text-red-600 hover:underline">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {galleries.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-10 text-center text-neutral-500">
                                        No galleries yet — use the Add gallery button above.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                    {galleryLastPage > 1 && <Paginator links={galleryLinks} />}
                </section>
            </div>
        </AppLayout>
    );
}
