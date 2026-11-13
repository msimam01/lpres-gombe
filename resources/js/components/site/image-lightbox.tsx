import { ChevronLeft, ChevronRight, X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

export interface LightboxImage {
    src: string;
    alt: string;
    caption?: string | null;
}

interface ImageLightboxProps {
    images: LightboxImage[];
    index: number;
    onClose: () => void;
    onIndexChange: (index: number) => void;
}

/**
 * Full-screen image viewer used by news articles: the featured photograph plus
 * every supporting image, browsable with the previous/next buttons, the arrow
 * keys, and closed with Escape or the close button. Rendered in a portal above
 * the sticky header, with page scrolling locked while open.
 */
export default function ImageLightbox({ images, index, onClose, onIndexChange }: ImageLightboxProps) {
    const closeRef = useRef<HTMLButtonElement>(null);
    const count = images.length;
    const current = images[index] ?? images[0];

    const go = (delta: number) => {
        if (count < 2) {
            return;
        }

        onIndexChange((index + delta + count) % count);
    };

    useEffect(() => {
        // Move focus into the viewer, then hand it back to the image button
        // that opened it so keyboard users keep their place.
        const trigger = document.activeElement as HTMLElement | null;
        closeRef.current?.focus();

        return () => {
            trigger?.focus?.();
        };
    }, []);

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, []);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(1);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(-1);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    if (!current) {
        return null;
    }

    const control =
        'focus-visible:outline-none flex size-11 shrink-0 items-center justify-center rounded-full bg-black/55 text-white ring-1 ring-white/30 backdrop-blur-sm transition-colors hover:bg-black/75 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#001f27] disabled:opacity-40';

    return createPortal(
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Article image viewer"
            className="fixed inset-0 z-[100] flex flex-col bg-[#001f27]/95 backdrop-blur-sm"
        >
            <div className="flex items-center justify-between px-4 py-4 sm:px-6">
                <p aria-live="polite" className="text-lpres-green text-xs font-extrabold tracking-[1px] uppercase">
                    Image {index + 1} of {count}
                </p>
                <button ref={closeRef} type="button" onClick={onClose} aria-label="Close image viewer" className={control}>
                    <X aria-hidden="true" className="size-5" />
                </button>
            </div>

            <div className="relative flex min-h-0 grow items-center justify-center px-4 pb-6 sm:px-16">
                <button
                    type="button"
                    onClick={() => go(-1)}
                    aria-label="Previous image"
                    disabled={count < 2}
                    className={`${control} absolute left-2 z-10 sm:left-4`}
                >
                    <ChevronLeft aria-hidden="true" className="size-6" />
                </button>

                <figure className="flex min-h-0 max-h-full flex-col items-center">
                    <img
                        src={current.src}
                        alt={current.alt}
                        className="block max-h-[calc(100vh-190px)] w-auto max-w-full object-contain"
                    />
                    {current.caption && (
                        <figcaption className="mt-3 max-w-3xl text-center text-sm leading-6 text-white/75">{current.caption}</figcaption>
                    )}
                </figure>

                <button
                    type="button"
                    onClick={() => go(1)}
                    aria-label="Next image"
                    disabled={count < 2}
                    className={`${control} absolute right-2 z-10 sm:right-4`}
                >
                    <ChevronRight aria-hidden="true" className="size-6" />
                </button>
            </div>
        </div>,
        document.body,
    );
}
