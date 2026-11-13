import LocationPicker, { type AdminMapConfig, type CoordinatePatch } from '@/components/admin/location-picker';
import { type AdminField, type AdminModule } from '@/pages/admin/resource/index';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import { FormEventHandler, ReactNode, useEffect, useState } from 'react';
import { toastFormError } from '@/lib/toast';

interface ResourceFormProps {
    module: AdminModule;
    record: Record<string, unknown>;
    map: AdminMapConfig;
}

/**
 * Which labelled section a field belongs to. The order below matches the
 * natural reading order of the form: details → content → media → publishing.
 */
const SECTION_LABELS = {
    details: 'Basic information',
    location: 'Location',
    content: 'Content',
    media: 'Media',
    publishing: 'Publishing',
} as const;

function sectionOf(field: AdminField): keyof typeof SECTION_LABELS {
    // Modules may place a field in an explicit section (e.g. the project
    // location block) without changing the generic renderer.
    if (field.section) {
        return field.section;
    }

    // Category selects carry the "Manage …" affordance and belong with the
    // record's basic details, not with the publishing controls.
    if (field.type === 'select' && field.manageHref) {
        return 'details';
    }

    switch (field.type) {
        case 'textarea':
        case 'beneficiary-table':
        case 'gallery':
            return 'content';
        case 'image':
        case 'document':
        case 'video':
            return 'media';
        case 'select':
        case 'date':
        case 'boolean':
            return 'publishing';
        default:
            return 'details';
    }
}

const SECTION_ORDER: (keyof typeof SECTION_LABELS)[] = ['details', 'location', 'content', 'media', 'publishing'];

/** Full width even on the two-column grid. */
const FULL_WIDTH_TYPES = new Set<AdminField['type']>(['textarea', 'beneficiary-table', 'gallery']);

/**
 * Multi-image gallery editor: existing images (each individually removable)
 * plus an appended preview for every newly picked file. The final order is
 * the shown order; the merged list is sent as gallery_keep + gallery_files.
 */
function GalleryField({
    field,
    value,
    onChanges,
}: {
    field: AdminField;
    value: unknown;
    onChanges: (key: string, value: unknown) => void;
}) {
    const existing = Array.isArray(value) ? (value as string[]) : [];
    const [kept, setKept] = useState<string[]>(existing);
    const [added, setAdded] = useState<File[]>([]);

    const sync = (nextKept: string[], nextAdded: File[]) => {
        setKept(nextKept);
        setAdded(nextAdded);
        onChanges(`gallery_${field.key}`, nextKept);
        onChanges(`files_${field.key}`, nextAdded);
    };

    // Mark the gallery section as present. An empty array is dropped from
    // multipart form data, so the server can otherwise not tell an edited
    // gallery from a form with no gallery section at all.
    useEffect(() => {
        onChanges(`gallery_${field.key}`, existing);
        onChanges(`gallery_has_${field.key}`, '1');
        // Mount-only registration: `existing` is the record's stored paths
        // captured once, and `onChanges` is the stable form.setData writer.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [field.key]);

    return (
        <div className="grid gap-3">
            {(kept.length > 0 || added.length > 0) && (
                <ul className="grid list-none grid-cols-3 gap-3 sm:grid-cols-4">
                    {kept.map((path, index) => (
                        <li key={path} className="relative">
                            <img
                                src={`/storage/${path}`}
                                alt={`Existing gallery image ${index + 1}`}
                                className="aspect-square w-full border border-[#e1e8ea] bg-[#f1f5f6] object-cover"
                            />
                            <button
                                type="button"
                                aria-label={`Remove existing gallery image ${index + 1}`}
                                onClick={() => sync(kept.filter((_, i) => i !== index), added)}
                                className="absolute top-1 right-1 grid size-7 place-items-center rounded-full bg-black/60 text-xs text-white hover:bg-red-600"
                            >
                                ✕
                            </button>
                            <span className="mt-1 block truncate text-[11px] text-neutral-500">{path.split('/').pop()}</span>
                        </li>
                    ))}
                    {added.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="relative">
                            <img
                                src={URL.createObjectURL(file)}
                                alt={`New image ${index + 1} preview`}
                                className="border-lpres-green/60 aspect-square w-full border-2 bg-[#f1f5f6] object-cover"
                            />
                            <button
                                type="button"
                                aria-label={`Remove new image ${index + 1}`}
                                onClick={() => sync(kept, added.filter((_, i) => i !== index))}
                                className="absolute top-1 right-1 grid size-7 place-items-center rounded-full bg-black/60 text-xs text-white hover:bg-red-600"
                            >
                                ✕
                            </button>
                            <span className="mt-1 block truncate text-[11px] text-neutral-500">
                                New: {file.name} (uploads on save)
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            <input
                id={`field-${field.key}`}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                onChange={(e) => sync(kept, [...added, ...Array.from(e.target.files ?? [])])}
                className="text-sm"
            />
            <p className="text-xs text-neutral-500">Existing images are kept unless removed here; new images upload when you save.</p>
        </div>
    );
}

/** ---------------------------------------------------------------------
 * Upload preview. Shows the freshly picked file (URL.createObjectURL) with
 * its filename before submitting, the currently stored image next to it, and
 * — for removable image fields — a "Remove current image" checkbox that the
 * server treats as bookkeeping, never a stored column.
 * ------------------------------------------------------------------- */
function FileField({
    field,
    value,
    onChanges,
}: {
    field: AdminField;
    value: unknown;
    onChanges: (key: string, value: unknown) => void;
}) {
    const isImage = field.type === 'image';
    const file = value instanceof File ? value : null;
    const currentPath = typeof value === 'string' ? value : '';
    const hasRemoveFlag = field.removable === true;
    const [removeCurrent, setRemoveCurrent] = useState(false);
    const revokeRef = { current: '' as string };

    return (
        <div className="grid gap-3">
            {isImage && currentPath !== '' && !file && !removeCurrent && (
                <div className="flex items-center gap-3">
                    <img
                        src={`/storage/${currentPath}`}
                        alt={`Current ${field.label.toLowerCase()}`}
                        className="border-[#e1e8ea] h-20 w-28 border bg-[#f1f5f6] object-cover"
                    />
                    <div className="text-xs text-neutral-500">
                        <p className="font-medium text-neutral-700">Current file</p>
                        <code className="break-all">{currentPath}</code>
                        <p>Leave untouched to keep it.</p>
                    </div>
                </div>
            )}

            <div className="grid gap-2">
                <label htmlFor={`field-${field.key}`} className="sr-only">
                    {field.label}
                </label>
                <input
                    id={`field-${field.key}`}
                    type="file"
                    accept={
                        field.type === 'image'
                            ? 'image/jpeg,image/png,image/webp'
                            : field.type === 'document'
                              ? '.pdf,.doc,.docx,.xls,.xlsx'
                              : 'video/mp4,video/webm'
                    }
                    onChange={(e) => {
                        const picked = e.target.files?.[0] ?? null;
                        onChanges(field.key, picked);
                        if (picked) {
                            // Picking a replacement cancels a pending removal.
                            setRemoveCurrent(false);
                        }
                    }}
                    className="text-sm"
                />
                {file && (
                    <div className="flex items-center gap-3">
                        {isImage && (
                            <img
                                src={URL.createObjectURL(file)}
                                alt="Selected file preview"
                                className="border-lpres-green/50 h-20 w-28 border bg-[#f1f5f6] object-cover"
                                onLoad={(event) => {
                                    // Free the object URL when done loading once.
                                    const url = (event.target as HTMLImageElement).src;
                                    if (revokeRef.current !== url) {
                                        revokeRef.current = url;
                                        setTimeout(() => URL.revokeObjectURL(url), 30000);
                                    }
                                }}
                            />
                        )}
                        <p className="text-xs text-neutral-600">
                            Selected: <span className="font-medium">{file.name}</span>
                            <span className="text-neutral-400"> ({Math.ceil(file.size / 1024)} KB)</span>
                            <span className="block text-neutral-500">Uploads when you save.</span>
                        </p>
                    </div>
                )}
            </div>

            {hasRemoveFlag && currentPath !== '' && !file && (
                <label className="flex items-center gap-2 text-sm text-neutral-600">
                    <input
                        type="checkbox"
                        checked={removeCurrent}
                        onChange={(e) => {
                            setRemoveCurrent(e.target.checked);
                            onChanges(`remove_${field.key}`, e.target.checked);
                        }}
                        className="size-4"
                    />
                    Remove the current image (saved when you submit)
                </label>
            )}
        </div>
    );
}

/** ---------------------------------------------------------------------
 * Value chain cluster beneficiary table editor: a repeatable, table-style
 * form. Rows carry their id (if existing) so the server can upsert instead
 * of blindly deleting. S/No is a derived row number, never editable.
 * ------------------------------------------------------------------- */
interface BeneficiaryRow {
    id?: number | null;
    lga?: string;
    ward?: string;
    beneficiaries?: string | number;
}

function BeneficiaryTableField({
    field,
    rows,
    onChanges,
    error,
}: {
    field: AdminField;
    rows: BeneficiaryRow[];
    onChanges: (key: string, value: unknown) => void;
    error?: string;
}) {
    const list = Array.isArray(rows) ? rows : [];
    const emptyRow: BeneficiaryRow = { id: null, lga: '', ward: '', beneficiaries: '' };

    const cellClass = 'h-9 w-full border border-input bg-background px-2 py-1 text-sm';

    const setRow = (index: number, patch: Partial<BeneficiaryRow>) => {
        const next = [...list];
        next[index] = { ...next[index], ...patch };
        onChanges(field.key, next);
    };

    const addRow = () => onChanges(field.key, [...list, { ...emptyRow }]);
    const removeRow = (index: number) => {
        const next = [...list];
        next.splice(index, 1);
        onChanges(field.key, next);
    };

    return (
        <div className="grid gap-3">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[540px] text-left text-sm">
                    <thead>
                        <tr className="border-b text-xs tracking-wider text-neutral-500 uppercase">
                            <th className="w-16 px-2 py-2 font-medium">S/No</th>
                            <th className="w-[30%] px-2 py-2 font-medium">LGA *</th>
                            <th className="w-[35%] px-2 py-2 font-medium">Ward *</th>
                            <th className="w-[25%] px-2 py-2 font-medium">Beneficiaries *</th>
                            <th className="w-12 px-2 py-2">
                                <span className="sr-only">Remove</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((row, index) => (
                            <tr key={row.id ?? `new-${index}`} className="border-b last:border-0">
                                <td className="px-2 py-1.5 text-neutral-500">{index + 1}</td>
                                <td className="px-2 py-1.5">
                                    <input
                                        aria-label={`Row ${index + 1}: LGA`}
                                        value={String(row.lga ?? '')}
                                        onChange={(e) => setRow(index, { lga: e.target.value })}
                                        className={cellClass}
                                    />
                                </td>
                                <td className="px-2 py-1.5">
                                    <input
                                        aria-label={`Row ${index + 1}: Ward`}
                                        value={String(row.ward ?? '')}
                                        onChange={(e) => setRow(index, { ward: e.target.value })}
                                        className={cellClass}
                                    />
                                </td>
                                <td className="px-2 py-1.5">
                                    <input
                                        aria-label={`Row ${index + 1}: Number of beneficiaries`}
                                        type="number"
                                        min={0}
                                        step={1}
                                        value={String(row.beneficiaries ?? '')}
                                        onChange={(e) => setRow(index, { beneficiaries: e.target.value })}
                                        className={cellClass}
                                    />
                                </td>
                                <td className="px-2 py-1.5 text-right">
                                    <button
                                        type="button"
                                        onClick={() => removeRow(index)}
                                        aria-label={`Remove row ${index + 1}`}
                                        className="text-red-600 hover:underline"
                                    >
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-2 py-6 text-center text-neutral-500">
                                    No beneficiary rows yet — add the first one below.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <button
                type="button"
                onClick={addRow}
                className="w-fit rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-neutral-100"
            >
                + Add row
            </button>

            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Field({
    field,
    value,
    onChanges,
    error,
    fullWidth = false,
}: {
    field: AdminField;
    value: unknown;
    onChanges: (key: string, value: unknown) => void;
    error?: string;
    fullWidth?: boolean;
}) {
    const wrap = `grid gap-2${fullWidth ? ' md:col-span-2' : ''}`;
    const common = 'h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

    return (
        <div className={wrap}>
            {field.type !== 'beneficiary-table' && (
                <label htmlFor={`field-${field.key}`} className="text-sm font-medium">
                    {field.label}
                    {field.required && <span className="ml-1 text-red-600">*</span>}
                </label>
            )}
            {field.type === 'beneficiary-table' && (
                <h3 className="text-sm font-medium">
                    {field.label}
                </h3>
            )}

            {field.help && <p className="-mt-1 text-xs text-neutral-500">{field.help}</p>}

            {field.type === 'textarea' && (
                <textarea
                    id={`field-${field.key}`}
                    rows={8}
                    value={String(value ?? '')}
                    onChange={(e) => onChanges(field.key, e.target.value)}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            )}

            {field.type === 'select' && (
                <>
                    <select
                        id={`field-${field.key}`}
                        value={String(value ?? '')}
                        onChange={(e) => onChanges(field.key, e.target.value)}
                        className={common}
                    >
                        {!field.required && field.allowEmpty !== false && (
                            <option value="">— {field.manageLabel ? 'No category yet' : 'None'} —</option>
                        )}
                        {field.options &&
                            Object.entries(field.options).map(([optionKey, optionLabel]) => (
                                <option key={optionKey} value={optionKey}>
                                    {optionLabel}
                                </option>
                            ))}
                    </select>
                    {field.manageHref && (
                        <Link href={field.manageHref} className="text-sm text-blue-600 hover:underline">
                            {field.manageLabel ?? 'Manage'} →
                        </Link>
                    )}
                </>
            )}

            {field.type === 'boolean' && (
                <label className="flex items-center gap-2 text-sm">
                    <input
                        id={`field-${field.key}`}
                        type="checkbox"
                        checked={Boolean(value)}
                        onChange={(e) => onChanges(field.key, e.target.checked)}
                        className="size-4"
                    />
                    Visible on the public site
                </label>
            )}

            {(field.type === 'text' || field.type === 'integer' || field.type === 'date') && (
                <input
                    id={`field-${field.key}`}
                    type={field.type === 'integer' ? 'number' : field.type}
                    min={field.type === 'integer' ? 0 : undefined}
                    value={String(value ?? '')}
                    onChange={(e) => onChanges(field.key, field.type === 'integer' ? Number(e.target.value) : e.target.value)}
                    className={common}
                />
            )}

            {(field.type === 'image' || field.type === 'document' || field.type === 'video') && (
                <FileField field={field} value={value} onChanges={onChanges} />
            )}

            {field.type === 'beneficiary-table' && (
                <BeneficiaryTableField
                    field={field}
                    rows={(value as BeneficiaryRow[]) ?? []}
                    onChanges={onChanges}
                    error={error}
                />
            )}

            {field.type === 'gallery' && <GalleryField field={field} value={value} onChanges={onChanges} />}

            {error && field.type !== 'beneficiary-table' && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border-border/70 rounded-xl border bg-white p-6">
            <h2 className="border-b border-[#e1e8ea] pb-3 text-base font-semibold">{title}</h2>
            <div className="mt-5 grid gap-5 md:grid-cols-2">{children}</div>
        </section>
    );
}

export default function ResourceForm({ module, record, map }: ResourceFormProps) {
    const isCreate = record.id === undefined || record.id === null;
    const routeBase = module.routeBase;
    // Media modules use a slash route path (e.g. media/images) but dotted route names.
    const routeName = routeBase.replace(/\//g, '.');

    const form = useForm<Record<string, FormDataConvertible>>({ ...record } as Record<string, FormDataConvertible>);
    const recordId = record.id as number;

    const isFileField = (key: string) =>
        module.fields.some((field) => field.key === key && ['image', 'document', 'video'].includes(field.type));

    /**
     * Edit without picking a new image must succeed and keep the stored
     * file: upload fields never replay their stored path string through the
     * server's `image` rule — an empty value means "keep current", a File
     * means "replace". Gallery pseudo-values likewise travel only through
     * the gallery_keep/gallery_files keys built by GalleryField.
     */
    const transformForSubmit = (data: Record<string, FormDataConvertible>, method: 'post' | 'put') => {
        const next: Record<string, FormDataConvertible> = { ...data };

        if (method === 'put') {
            next._method = 'put'; // Method spoofing for multipart updates.
        }

        for (const key of Object.keys(next)) {
            if (isFileField(key) && !(next[key] instanceof File)) {
                next[key] = '';
            }

            if (module.fields.some((field) => field.type === 'gallery' && field.key === key)) {
                delete next[key];
            }
        }

        return next;
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (isCreate) {
            form.transform((data) => transformForSubmit(data, 'post'));
            form.post(route(`admin.${routeName}.store`), {
                forceFormData: true,
                onError: () => toastFormError(),
            });
        } else {
            form.transform((data) => transformForSubmit(data, 'put'));
            form.post(route(`admin.${routeName}.update`, recordId), {
                forceFormData: true,
                onError: () => toastFormError(),
            });
        }
    };

    // Group the module's fields into labelled sections in declaration order
    // of the section list, preserving each field's own order inside a group.
    const sections = SECTION_ORDER.map((key) => ({
        key,
        title: SECTION_LABELS[key],
        fields: module.fields.filter((field) => sectionOf(field) === key),
    })).filter((section) => section.fields.length > 0);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/dashboard' },
                { title: module.label, href: `/admin/${routeBase}` },
                { title: isCreate ? 'Create' : 'Edit', href: '' },
            ]}
        >
            <Head title={`${isCreate ? 'New' : 'Edit'} ${module.label.toLowerCase()}`} />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <header>
                    <h1 className="text-2xl font-extrabold text-[#223645]">
                        {isCreate ? 'New' : 'Edit'} {module.label.toLowerCase()}
                    </h1>
                    <p className="mt-1 text-sm text-[#838e91]">
                        Fields marked <span className="font-semibold text-red-600">*</span> are required. Saved changes appear on the public
                        website immediately.
                    </p>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                    {sections.map((section) => (
                        <Section key={section.key} title={section.title}>
                            {section.fields.map((field) =>
                                field.type === 'location' ? (
                                    <div key={field.key} className="grid gap-2 md:col-span-2">
                                        <h3 className="text-sm font-medium">{field.label}</h3>
                                        {field.help && <p className="text-xs text-neutral-500">{field.help}</p>}
                                        <LocationPicker
                                            latitude={form.data.latitude}
                                            longitude={form.data.longitude}
                                            onChange={(patch: CoordinatePatch) => form.setData({ ...form.data, ...patch })}
                                            errors={{ latitude: form.errors.latitude, longitude: form.errors.longitude }}
                                            map={map}
                                        />
                                    </div>
                                ) : (
                                    <Field
                                        key={field.key}
                                        field={field}
                                        value={form.data[field.key]}
                                        onChanges={(key, value) => form.setData(key, value as FormDataConvertible)}
                                        error={form.errors[field.key]}
                                        fullWidth={FULL_WIDTH_TYPES.has(field.type)}
                                    />
                                ),
                            )}
                        </Section>
                    ))}

                    <div className="border-border/70 flex items-center gap-3 rounded-xl border bg-white p-6">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-lpres-green hover:bg-lpres-deep rounded-md px-5 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50"
                        >
                        {isCreate ? 'Create' : 'Save changes'}
                    </button>
                        <Link
                            href={route(`admin.${routeName}.index`)}
                            className="text-sm text-neutral-600 hover:underline"
                        >
                            Cancel
                        </Link>
                        {form.wasSuccessful && (
                            <p role="status" className="text-sm text-green-700">
                                Saved.
                            </p>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
