import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AtSign } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Newsletter subscribers', href: '/admin/subscribers' },
];

interface Subscriber {
    id: number;
    email: string;
    created_at: string;
}

export default function Subscribers({ subscribers }: { subscribers: { data: Subscriber[] } }) {
    const remove = (subscriber: Subscriber) => {
        if (window.confirm(`Remove ${subscriber.email}?`)) {
            router.delete(route('admin.subscribers.destroy', subscriber.id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Newsletter subscribers" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">Newsletter subscribers</h1>
                    <p className="mt-1 text-sm text-[#838e91]">Visitors who opted in on the public website. {subscribers.data.length} in total.</p>
                </header>
                <ul className="border-border/70 overflow-hidden rounded-xl border bg-white">
                    {subscribers.data.map((subscriber) => (
                        <li key={subscriber.id} className="border-border/60 flex items-center gap-3 border-b px-4 py-3 text-sm transition-colors last:border-0 hover:bg-[#f7fafb]">
                            <AtSign aria-hidden="true" className="text-lpres-green size-4 shrink-0" />
                            <span className="font-medium">{subscriber.email}</span>
                            <span className="text-neutral-500">joined {subscriber.created_at}</span>
                            <button onClick={() => remove(subscriber)} className="ml-auto text-red-600 hover:underline">
                                Remove
                            </button>
                        </li>
                    ))}
                    {subscribers.data.length === 0 && (
                        <li className="p-12 text-center">
                            <AtSign aria-hidden="true" className="mx-auto mb-3 size-8 text-neutral-300" />
                            <p className="text-lg font-semibold text-[#223645]">No subscribers yet</p>
                            <p className="mt-1 text-sm text-neutral-500">Newsletter sign-ups from the public website will appear here.</p>
                        </li>
                    )}
                </ul>
            </div>
        </AppLayout>
    );
}
