import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import SectionHeading from '@/components/site/section-heading';
import { FolderOpen, Images, MapPin, PlayCircle } from 'lucide-react';

export interface GalleryCard {
    id: number;
    title: string;
    description: string | null;
    cover: string | null;
    imageCount: number;
    href: string;
    seenOnLabel: string | null;
    seenOnHref: string | null;
}

export interface MediaCard {
    id: number;
    title: string;
    type: string;
    image: string | null;
    thumbnail: string | null;
    videoUrl: string | null;
    caption: string | null;
    seenOnLabel: string | null;
    seenOnHref: string | null;
}

interface MediaProps {
    galleries: GalleryCard[];
    images: MediaCard[];
    videos: MediaCard[];
}

/** Build the embeddable URL for a YouTube/Vimeo watch link, else null. */
function toEmbedUrl(url: string): string | null {
    const youtube = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/);
    if (youtube) {
        return `https://www.youtube-nocookie.com/embed/${youtube[1]}?autoplay=1&rel=0`;
    }
    const vimeo = url.match(/vimeo\.com\/(\d+)/);
    if (vimeo) {
        return `https://player.vimeo.com/video/${vimeo[1]}?autoplay=1`;
    }
    return null;
}

/**
 * The public Media page: image galleries, standalone image items and YouTube
 * videos, each in its own clearly labelled section, with links to the event,
 * news article or project record an item belongs to. Only published content
 * arrives from the server.
 */
export default function Media({ galleries, images, videos }: MediaProps) {
    const [openId, setOpenId] = useState<number | null>(null);
    const closeRef = useRef<HTMLButtonElement>(null);

    const flatMedia = [...images, ...videos];
    const openItem = flatMedia.find((item) => item.id === openId) ?? null;
    const embedUrl = openItem?.videoUrl ? toEmbedUrl(openItem.videoUrl) : null;

    const close = useCallback(() => setOpenId(null), []);

    useEffect(() => {
        if (openId === null) {
            return undefined;
        }

        const previous = document.activeElement as HTMLElement | null;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        document.addEventListener('keydown', onKeyDown);
        closeRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            previous?.focus();
        };
    }, [openId, close]);

    const linkTo = (item: MediaCard) =>
        item.seenOnLabel && item.seenOnHref ? (
            <Link
                href={item.seenOnHref}
                onClick={(event) => event.stopPropagation()}
                className="text-lpres-green mt-2 inline-flex items-center gap-1.5 text-xs font-extrabold tracking-[0.5px] uppercase hover:underline"
            >
                <MapPin aria-hidden="true" className="size-3.5" />
                {item.seenOnLabel}
            </Link>
        ) : null;

    return (
        <PublicPage
            title="Media"
            eyebrow="Photo & Video"
            description="Photos, galleries and videos from the Gombe State L-PRES project."
        >
            {galleries.length === 0 && images.length === 0 && videos.length === 0 ? (
                <EmptyState message="No media has been published yet. Photos, galleries and videos added in the admin panel will appear here." />
            ) : (
                <div className="space-y-16">
                    {galleries.length > 0 && (
                        <section aria-labelledby="media-galleries-heading">
                            <SectionHeading eyebrow="Photo collections" title="Galleries" id="media-galleries-heading" />
                            <ul className="mt-8 grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                                {galleries.map((gallery) => (
                                    <li key={gallery.id}>
                                        <Link
                                            href={gallery.href}
                                            className="group block h-full border border-[#e1e8ea] bg-white no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)]"
                                        >
                                            <div className="relative overflow-hidden">
                                                {gallery.cover ? (
                                                    <img
                                                        src={gallery.cover}
                                                        alt={gallery.title}
                                                        width={465}
                                                        height={375}
                                                        loading="lazy"
                                                        decoding="async"
                                                        className="block aspect-[465/375] w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                                                    />
                                                ) : (
                                                    <div
                                                        aria-hidden="true"
                                                        className="flex aspect-[465/375] w-full items-center justify-center bg-lpres-deep"
                                                    >
                                                        <FolderOpen className="size-12 text-white/30" aria-hidden="true" />
                                                    </div>
                                                )}
                                                <span className="text-lpres-deep absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-sm bg-white/90 px-3 py-1 text-[11px] font-extrabold uppercase">
                                                    <Images aria-hidden="true" className="size-3.5" />
                                                    {gallery.imageCount} {gallery.imageCount === 1 ? 'image' : 'images'}
                                                </span>
                                            </div>
                                            <div className="px-[26px] py-6">
                                                <h2 className="text-lg leading-7 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                                    {gallery.title}
                                                </h2>
                                                {gallery.description && (
                                                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-[#838e91]">{gallery.description}</p>
                                                )}
                                                {(gallery.seenOnLabel && gallery.seenOnHref) && (
                                                    <Link
                                                        href={gallery.seenOnHref}
                                                        onClick={(event) => event.stopPropagation()}
                                                        className="text-lpres-green mt-3 inline-flex items-center gap-1.5 text-xs font-extrabold tracking-[0.5px] uppercase hover:underline"
                                                    >
                                                        <MapPin aria-hidden="true" className="size-3.5" />
                                                        {gallery.seenOnLabel}
                                                    </Link>
                                                )}
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {videos.length > 0 && (
                        <section aria-labelledby="media-videos-heading">
                            <SectionHeading eyebrow="Watch" title="Videos" id="media-videos-heading" />
                            <ul className="mt-8 grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                                {videos.map((item) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => setOpenId(item.id)}
                                            className="group block h-full w-full border border-[#e1e8ea] bg-white text-center no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)]"
                                            aria-label={`Play video: ${item.title}`}
                                        >
                                            <div className="relative overflow-hidden">
                                                {item.thumbnail ? (
                                                    <img
                                                        src={item.thumbnail}
                                                        alt={item.title}
                                                        width={465}
                                                        height={375}
                                                        loading="lazy"
                                                        decoding="async"
                                                        className="block aspect-[465/375] w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                                                    />
                                                ) : (
                                                    <div
                                                        aria-hidden="true"
                                                        className="flex aspect-[465/375] w-full items-center justify-center bg-lpres-deep"
                                                    >
                                                        <PlayCircle className="size-12 text-white/30" aria-hidden="true" />
                                                    </div>
                                                )}
                                                <span
                                                    aria-hidden="true"
                                                    className="absolute top-1/2 left-1/2 grid size-14 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-lpres-deep transition-transform duration-500 group-hover:scale-110"
                                                >
                                                    ▶
                                                </span>
                                            </div>
                                            <div className="px-5 py-5">
                                                <h2 className="text-lg leading-7 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                                    {item.title}
                                                </h2>
                                                {item.caption && <p className="mt-1 text-sm leading-6 text-[#838e91]">{item.caption}</p>}
                                                {linkTo(item)}
                                            </div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {images.length > 0 && (
                        <section aria-labelledby="media-images-heading">
                            <SectionHeading eyebrow="Photo highlights" title="Images" id="media-images-heading" />
                            <ul className="mt-8 grid list-none gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                {images.map((item) => (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() => setOpenId(item.id)}
                                            className="group block h-full w-full border border-[#e1e8ea] bg-[#f1f5f6] text-center no-underline"
                                            aria-label={`Open the full image: ${item.title}`}
                                        >
                                            {item.image ? (
                                                <img
                                                    src={item.image}
                                                    alt={item.title}
                                                    width={370}
                                                    height={280}
                                                    loading="lazy"
                                                    decoding="async"
                                                    className="block aspect-[370/280] w-full object-cover transition-[filter] duration-500 group-hover:brightness-110"
                                                />
                                            ) : (
                                                <div aria-hidden="true" className="block aspect-[370/280] w-full bg-lpres-deep" />
                                            )}
                                            <div className="px-4 py-5">
                                                <h2 className="text-lg leading-7 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                                    {item.title}
                                                </h2>
                                                {item.caption && <p className="mt-1 text-sm leading-6 text-[#838e91]">{item.caption}</p>}
                                                {linkTo(item)}
                                            </div>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>
            )}

            {openItem && (
                <div role="dialog" aria-modal="true" aria-label={openItem.title} className="fixed inset-0 z-[70] flex items-center justify-center bg-black/85 p-6">
                    <button
                        ref={closeRef}
                        type="button"
                        onClick={close}
                        aria-label="Close viewer"
                        className="text-3xl absolute top-4 right-4 size-12 cursor-pointer font-bold text-white"
                    >
                        ×
                    </button>

                    <figure className="max-h-full max-w-full">
                        {openItem.type === 'video' && openItem.videoUrl ? (
                            embedUrl ? (
                                <iframe
                                    src={embedUrl}
                                    title={openItem.title}
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture"
                                    allowFullScreen
                                    className="aspect-video max-h-[80vh] w-[min(90vw,142vh)] max-w-full border-0"
                                />
                            ) : (
                                <video src={openItem.videoUrl} controls playsInline className="max-h-[80vh] max-w-full border-0" />
                            )
                        ) : openItem.image ? (
                            <img src={openItem.image} alt={openItem.title} className="max-h-[80vh] max-w-full object-contain" />
                        ) : null}

                        <figcaption className="mt-4 text-center text-sm text-white/90">
                            {openItem.caption ?? openItem.title}
                            {openItem.seenOnLabel && <span className="mt-1 block text-white/70">{openItem.seenOnLabel}</span>}
                        </figcaption>
                    </figure>
                </div>
            )}
        </PublicPage>
    );
}
