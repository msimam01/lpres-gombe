import { useEffect, useRef, useState } from 'react';

/**
 * Accessible inline gallery for the Visual Reports page. Tiles open the
 * report's uploaded visual in a lightbox with close (button + Escape), focus
 * restore, and next/previous keyboard navigation (← / →) without distorting
 * the original image.
 */
interface VisualReportsGalleryProps {
    reports: {
        id: number;
        title: string;
        description: string | null;
        image: string | null;
        file: string | null;
    }[];
}

export default function VisualReportsGallery({ reports }: VisualReportsGalleryProps) {
    const [openIndex, setOpenIndex] = useState<number | null>(null);
    const closeRef = useRef<HTMLButtonElement>(null);

    const close = () => setOpenIndex(null);
    const step = (delta: number) =>
        setOpenIndex((current) => {
            if (current === null) {
                return null;
            }

            return (current + delta + reports.length) % reports.length;
        });

    useEffect(() => {
        if (openIndex === null) {
            return undefined;
        }

        const previous = document.activeElement as HTMLElement | null;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }

            if (event.key === 'ArrowRight') {
                step(1);
            }

            if (event.key === 'ArrowLeft') {
                step(-1);
            }
        };

        document.addEventListener('keydown', onKeyDown);
        closeRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            previous?.focus();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [openIndex, reports.length]);

    const open = openIndex === null ? null : reports[openIndex];

    return (
        <>
            <ul className="grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                {reports.map((report, index) => (
                    <li key={report.id}>
                        <button
                            type="button"
                            onClick={() => setOpenIndex(index)}
                            className="group block h-full w-full bg-[#f1f5f6] text-center no-underline"
                            aria-label={`View full image: ${report.title}`}
                        >
                            {report.image ? (
                                <img
                                    src={report.image}
                                    alt={report.title}
                                    width={370}
                                    height={280}
                                    loading="lazy"
                                    decoding="async"
                                    className="block aspect-[370/280] w-full bg-lpres-mist object-cover transition-[filter] duration-500 group-hover:brightness-110"
                                />
                            ) : (
                                <div aria-hidden="true" className="block aspect-[370/280] w-full bg-lpres-deep" />
                            )}
                            <div className="bg-[#f1f5f6] px-6 py-5 text-center">
                                <h2 className="text-lg leading-7 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                    {report.title}
                                </h2>
                                {report.description && <p className="mt-1 text-sm leading-6 text-[#838e91]">{report.description}</p>}
                                <span className="text-lpres-green mt-3 inline-block text-xs font-bold tracking-[1px] uppercase">
                                    {report.file ? 'View / download' : 'View full image'}
                                </span>
                            </div>
                        </button>
                    </li>
                ))}
            </ul>

            {open && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label={`Visual report: ${open.title}`}
                    className="fixed inset-0 z-[80] flex items-center justify-center bg-black/85 p-4 sm:p-8"
                    onClick={(event) => {
                        if (event.target === event.currentTarget) {
                            close();
                        }
                    }}
                >
                    <button
                        ref={closeRef}
                        type="button"
                        onClick={close}
                        aria-label="Close report view"
                        className="absolute top-4 right-4 grid size-11 place-items-center rounded-full bg-white/90 text-2xl leading-none text-[#223645] hover:bg-white"
                    >
                        ×
                    </button>

                    {reports.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={() => step(-1)}
                                aria-label="Previous report"
                                className="absolute top-1/2 left-2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl text-[#223645] hover:bg-white sm:left-4"
                            >
                                ‹
                            </button>
                            <button
                                type="button"
                                onClick={() => step(1)}
                                aria-label="Next report"
                                className="absolute top-1/2 right-2 grid size-11 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl text-[#223645] hover:bg-white sm:right-4"
                            >
                                ›
                            </button>
                        </>
                    )}

                    <figure className="flex max-h-full max-w-full flex-col items-center gap-4">
                        {open.image ? (
                            <img
                                src={open.image}
                                alt={open.title}
                                className="max-h-[70vh] max-w-full object-contain"
                            />
                        ) : (
                            <div className="grid h-[40vh] w-[60vw] place-items-center bg-black/40 text-white">
                                No image for this report yet.
                            </div>
                        )}
                        <figcaption className="text-center text-white">
                            <p className="text-lg font-bold">{open.title}</p>
                            {open.description && <p className="mt-1 text-sm text-white/80">{open.description}</p>}
                            {open.file && (
                                <a
                                    href={open.file}
                                    download
                                    className="mt-3 inline-block bg-[#223645] px-5 py-2 text-sm font-bold text-white no-underline hover:bg-[#00a651]"
                                >
                                    Download report file
                                </a>
                            )}
                        </figcaption>
                    </figure>
                </div>
            )}
        </>
    );
}
