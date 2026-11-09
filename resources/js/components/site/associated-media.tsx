import { Link } from '@inertiajs/react';
import { Image as ImageIcon, Images, PlayCircle } from 'lucide-react';

/**
 * Media coverage block shared by the public news, event and project detail
 * pages: published galleries, videos and standalone image items associated
 * with the record, each clearly distinguished.
 */
export default function AssociatedMedia({
    galleries,
    videos,
    images,
}: {
    galleries: { title: string; href: string; imageCount: number; cover: string | null }[];
    videos: { title: string; caption: string | null; videoUrl: string; thumbnail: string | null }[];
    images: { title: string; caption: string | null; src: string | null }[];
}) {
    if (galleries.length === 0 && videos.length === 0 && images.length === 0) {
        return null;
    }

    return (
        <section aria-label="Associated media" className="mt-10 border-t border-[#e1e8ea] pt-8">
            <h3 className="text-lpres-deep text-xl font-extrabold">Media Coverage</h3>

            {galleries.length > 0 && (
                <ul className="mt-5 grid list-none gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {galleries.map((entry) => (
                        <li key={entry.href}>
                            <Link
                                href={entry.href}
                                className="group block border border-[#e1e8ea] bg-[#f1f5f6] no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1 hover:shadow-[0_18px_32px_-18px_rgba(0,59,73,0.35)]"
                            >
                                {entry.cover && (
                                    <img
                                        src={entry.cover}
                                        alt=""
                                        aria-hidden="true"
                                        width={640}
                                        height={420}
                                        loading="lazy"
                                        decoding="async"
                                        className="block aspect-[640/420] w-full object-cover"
                                    />
                                )}
                                <div className="p-5">
                                    <p className="text-lpres-green inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase">
                                        <Images aria-hidden="true" className="size-4" />
                                        Gallery · {entry.imageCount} {entry.imageCount === 1 ? 'image' : 'images'}
                                    </p>
                                    <h4 className="group-hover:text-lpres-green mt-2 text-lg leading-7 font-extrabold text-lpres-deep transition-colors">
                                        {entry.title}
                                    </h4>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {videos.length > 0 && (
                <ul className="mt-5 list-none divide-y divide-[#e1e8ea] rounded-sm border border-[#e1e8ea] bg-[#f1f5f6]">
                    {videos.map((video) => (
                        <li key={video.videoUrl} className="p-4">
                            <a
                                href={video.videoUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="group block no-underline"
                            >
                                <span className="text-lpres-deep inline-flex items-center gap-3 text-base leading-6 font-bold transition-colors group-hover:text-lpres-green">
                                    <PlayCircle aria-hidden="true" className="size-5 shrink-0" />
                                    {video.title}
                                </span>
                                {video.caption && <span className="mt-1 block text-sm leading-6 text-[#838e91]">{video.caption}</span>}
                            </a>
                        </li>
                    ))}
                </ul>
            )}

            {images.length > 0 && (
                <ul className="mt-5 grid list-none gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {images.map((image, index) => (
                        <li key={`${image.src ?? image.title}-${index}`} className="border border-[#e1e8ea] bg-[#f1f5f6] p-0">
                            {image.src && (
                                <img
                                    src={image.src}
                                    alt={image.title}
                                    width={640}
                                    height={420}
                                    loading="lazy"
                                    decoding="async"
                                    className="block aspect-[640/420] w-full object-cover"
                                />
                            )}
                            <div className="p-4">
                                <p className="text-lpres-green inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase">
                                    <ImageIcon aria-hidden="true" className="size-4" />
                                    Image
                                </p>
                                <h4 className="text-lpres-deep mt-1 text-base leading-6 font-extrabold">{image.title}</h4>
                                {image.caption && <p className="mt-1 text-sm leading-6 text-[#838e91]">{image.caption}</p>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <p className="mt-6">
                <Link
                    href="/media"
                    className="border-lpres-green inline-flex h-[46px] items-center gap-3 border px-6 text-xs font-extrabold tracking-[0.5px] text-lpres-deep uppercase transition-colors duration-500 hover:bg-lpres-green hover:text-white"
                >
                    View all media
                </Link>
            </p>
        </section>
    );
}
