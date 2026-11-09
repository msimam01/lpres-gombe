import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/admin/settings' },
];

interface SettingsProps {
    settings: Record<string, string | null>;
}

interface SettingsFormShape {
    [key: string]: string | null | undefined | File;
}

const generalFields: { key: string; label: string; type: 'text' | 'email' | 'url' }[] = [
    { key: 'contact_address', label: 'Contact address', type: 'text' },
    { key: 'contact_email', label: 'Contact email', type: 'email' },
    { key: 'contact_phone', label: 'Contact phone', type: 'text' },
];

const socialFields: { key: string; label: string; type: 'url' }[] = [
    { key: 'social_facebook', label: 'Facebook URL', type: 'url' },
    { key: 'social_x', label: 'X (Twitter) URL', type: 'url' },
    { key: 'social_linkedin', label: 'LinkedIn URL', type: 'url' },
    { key: 'social_youtube', label: 'YouTube URL', type: 'url' },
];

function TextField({
    id,
    label,
    type,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    type: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

export default function Settings({ settings }: SettingsProps) {
    const form = useForm<SettingsFormShape>({
        ...Object.fromEntries([...generalFields, ...socialFields].map((field) => [field.key, settings[field.key] ?? ''])),
        logo_path: null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.put(route('admin.settings.update'), { forceFormData: form.data.logo_path instanceof File });
    };

    const gridClass = 'grid gap-5 md:grid-cols-2';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Site settings" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">Site settings</h1>
                    <p className="mt-1 text-sm text-[#838e91]">
                        These values drive the public footer, the contact page, the auth panel and the header logo.
                    </p>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                    <section className="border-border/70 rounded-xl border bg-white p-6">
                        <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">Contact information</h2>
                        <div className={gridClass}>
                            {generalFields.map((field) => (
                                <TextField
                                    key={field.key}
                                    id={`setting-${field.key}`}
                                    label={field.label}
                                    type={field.type}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(value) => form.setData(field.key, value)}
                                    error={form.errors[field.key]}
                                />
                            ))}
                        </div>
                    </section>

                    <section className="border-border/70 rounded-xl border bg-white p-6">
                        <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">Social links</h2>
                        <div className={gridClass}>
                            {socialFields.map((field) => (
                                <TextField
                                    key={field.key}
                                    id={`setting-${field.key}`}
                                    label={field.label}
                                    type={field.type}
                                    value={String(form.data[field.key] ?? '')}
                                    onChange={(value) => form.setData(field.key, value)}
                                    error={form.errors[field.key]}
                                />
                            ))}
                        </div>
                    </section>

                    <section className="border-border/70 rounded-xl border bg-white p-6">
                        <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">Public logo</h2>
                        <div className="grid gap-2">
                            <label htmlFor="setting-logo" className="text-sm font-medium">
                                Logo image (current: {settings.logo_path || 'built-in'})
                            </label>
                            <input
                                id="setting-logo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) => form.setData('logo_path', e.target.files?.[0] ?? null)}
                                className="text-sm"
                            />
                            {form.errors.logo_path && <p className="text-sm text-red-600">{form.errors.logo_path}</p>}
                        </div>
                    </section>

                    <div className="border-border/70 flex items-center gap-3 rounded-xl border bg-white p-6">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lpres-green hover:bg-lpres-deep rounded-md px-5 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            Save settings
                        </button>
                        {form.wasSuccessful && <p className="font-semibold text-lpres-deep">Saved.</p>}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
