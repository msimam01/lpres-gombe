import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Media', href: '/admin/media' },
    { title: 'Add images', href: '/admin/media/images/bulk' },
];

interface BulkImagesProps {
    associations: Record<string, string>;
}

interface BulkImageForm {
    [key: string]: FormDataConvertible;
    images: File[];
    caption: string;
    associated: string;
    status: string;
}

/**
 * "Add images": several files in one submit. Each image becomes its own
 * media item (title derived from the filename) so it can be edited, published
 * or deleted individually afterwards, exactly like a single-added image.
 */
export default function BulkImages({ associations }: BulkImagesProps) {
    const form = useForm<BulkImageForm>({
        images: [],
        caption: '',
        associated: '',
        status: 'published',
    });
    const [previews, setPreviews] = useState<{ name: string; size: number; url: string }[]>([]);

    const selectFiles = (files: FileList | null) => {
        const picked = Array.from(files ?? []).slice(0, 30);

        previews.forEach((preview) => URL.revokeObjectURL(preview.url));
        setPreviews(
            picked.map((file) => ({ name: file.name, size: file.size, url: URL.createObjectURL(file) })),
        );
        form.setData('images', picked);
        form.clearErrors('images');
    };

    const totalSize = useMemo(() => form.data.images.reduce((sum, file) => sum + file.size, 0), [form.data.images]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        form.post(route('admin.media.images.bulk-store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                previews.forEach((preview) => URL.revokeObjectURL(preview.url));
                setPreviews([]);
                form.reset();
            },
        });
    };

    const error = form.errors.images as string | undefined;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add images" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">Add images</h1>
                    <p className="mt-1 text-sm text-[#838e91]">
                        Choose one or several images. Each file is saved as its own media item, titled from its filename and ready to edit or
                        publish individually.
                    </p>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                    <section className="border-border/70 rounded-xl border bg-white p-6">
                        <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">Images</h2>

                        <div className="mt-5 grid gap-4">
                            <div className="grid gap-2">
                                <label htmlFor="bulk-images" className="text-sm font-medium">
                                    Image files <span className="text-red-600">*</span>
                                </label>
                                <input
                                    id="bulk-images"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    onChange={(event) => selectFiles(event.target.files)}
                                    className="text-sm"
                                />
                                <p className="text-xs text-neutral-500">JPG, PNG or WebP · up to 4 MB each · up to 30 at a time.</p>
                                {error && <p className="text-sm text-red-600">{error}</p>}
                            </div>

                            {previews.length > 0 && (
                                <>
                                    <p className="text-sm font-medium text-neutral-700">
                                        {previews.length} {previews.length === 1 ? 'image' : 'images'} selected
                                        <span className="font-normal text-neutral-500"> · {Math.ceil(totalSize / 1024)} KB total</span>
                                    </p>
                                    <ul className="grid list-none grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                        {previews.map((preview, index) => (
                                            <li key={`${preview.name}-${index}`} className="border border-[#e1e8ea] bg-[#f1f5f6]">
                                                <img
                                                    src={preview.url}
                                                    alt={`Selected image ${index + 1}: ${preview.name}`}
                                                    className="aspect-square w-full object-cover"
                                                />
                                                <span className="block truncate px-2 py-1.5 text-[11px] text-neutral-600">{preview.name}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>
                    </section>

                    <section className="border-border/70 rounded-xl border bg-white p-6">
                        <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">Details</h2>
                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <div className="grid gap-2 md:col-span-2">
                                <label htmlFor="bulk-caption" className="text-sm font-medium">
                                    Description
                                </label>
                                <textarea
                                    id="bulk-caption"
                                    rows={3}
                                    value={form.data.caption}
                                    onChange={(event) => form.setData('caption', event.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                                <p className="text-xs text-neutral-500">Optional, applied to every image in this batch.</p>
                                {form.errors.caption && <p className="text-sm text-red-600">{form.errors.caption}</p>}
                            </div>

                            <div className="grid gap-2">
                                <label htmlFor="bulk-associated" className="text-sm font-medium">
                                    Associated content
                                </label>
                                <select
                                    id="bulk-associated"
                                    value={form.data.associated}
                                    onChange={(event) => form.setData('associated', event.target.value)}
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    {Object.entries(associations).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <label htmlFor="bulk-status" className="text-sm font-medium">
                                    Status
                                </label>
                                <select
                                    id="bulk-status"
                                    value={form.data.status}
                                    onChange={(event) => form.setData('status', event.target.value)}
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="published">Published</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <div className="border-border/70 flex items-center gap-3 rounded-xl border bg-white p-6">
                        <button
                            type="submit"
                            disabled={form.processing || form.data.images.length === 0}
                            className="bg-lpres-green hover:bg-lpres-deep inline-flex items-center gap-2 rounded-md px-5 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                            <ImagePlus aria-hidden="true" className="size-4" />
                            {form.processing
                                ? 'Uploading…'
                                : form.data.images.length > 0
                                  ? `Upload ${form.data.images.length} ${form.data.images.length === 1 ? 'image' : 'images'}`
                                  : 'Upload images'}
                        </button>
                        <Link href="/admin/media" className="text-sm text-neutral-600 hover:underline">
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
