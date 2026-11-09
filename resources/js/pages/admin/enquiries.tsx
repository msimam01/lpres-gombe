import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Mail } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contact enquiries', href: '/admin/enquiries' },
];

interface Enquiry {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    message: string;
    handled_at: string | null;
}

interface EnquiriesProps {
    enquiries: {
        data: Enquiry[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export default function Enquiries({ enquiries }: EnquiriesProps) {
    const toggle = (id: number) => {
        router.post(route('admin.enquiries.toggle', id), {}, { preserveScroll: true });
    };

    const destroy = (enquiry: Enquiry) => {
        if (window.confirm(`Delete the enquiry from "${enquiry.name}"?`)) {
            router.delete(route('admin.enquiries.destroy', enquiry.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contact enquiries" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">Contact enquiries</h1>
                    <p className="mt-1 text-sm text-[#838e91]">
                        Messages received before public forms were retired. Record {enquiries.data.length} in total.
                    </p>
                </header>

                <ul className="space-y-3">
                    {enquiries.data.map((enquiry) => (
                        <li
                            key={enquiry.id}
                            className={`border-border/70 rounded-xl border bg-white p-4 ${enquiry.handled_at ? 'opacity-70' : ''}`}
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                <p className="font-medium">{enquiry.name}</p>
                                <a href={`mailto:${enquiry.email}`} className="text-sm text-blue-600 hover:underline">
                                    {enquiry.email}
                                </a>
                                {enquiry.phone && <span className="text-sm text-neutral-600">· {enquiry.phone}</span>}
                                <span
                                    className={`ml-auto rounded-full px-2.5 py-0.5 text-xs font-semibold ${enquiry.handled_at ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}
                                >
                                    {enquiry.handled_at ? 'Handled' : 'New'}
                                </span>
                            </div>
                            <p className="mt-3 text-sm leading-6 whitespace-pre-line">{enquiry.message}</p>
                            <div className="mt-3 flex gap-3">
                                <button onClick={() => toggle(enquiry.id)} className="text-sm text-purple-700 hover:underline">
                                    {enquiry.handled_at ? 'Reopen' : 'Mark handled'}
                                </button>
                                <button onClick={() => destroy(enquiry)} className="text-sm text-red-600 hover:underline">
                                    Delete
                                </button>
                            </div>
                        </li>
                    ))}
                    {enquiries.data.length === 0 && (
                        <li className="border-border/70 rounded-xl border bg-white p-12 text-center">
                            <Mail aria-hidden="true" className="mx-auto mb-3 size-8 text-neutral-300" />
                            <p className="text-lg font-semibold text-[#223645]">No enquiries</p>
                            <p className="mt-1 text-sm text-neutral-500">Visitor messages will appear here when they are received.</p>
                        </li>
                    )}
                </ul>

                {enquiries.links && enquiries.links.length > 3 && (
                    <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                        {enquiries.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveScroll
                                    className={`rounded border px-3 py-1 text-sm ${link.active ? 'bg-lpres-green text-white' : 'hover:bg-neutral-100'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : null,
                        )}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
