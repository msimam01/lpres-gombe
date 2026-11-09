import ImageGallery from '@/components/site/image-gallery';
import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import { Link } from '@inertiajs/react';
import { Images, MapPin } from 'lucide-react';

interface GalleryShowProps {
    gallery: {
        title: string;
        description: string | null;
        images: { src: string; alt: string }[];
        seenOnLabel: string | null;
        seenOnHref: string | null;
    };
    otherGalleries: { title: string; href: string }[];
}

export default function GalleryShow({ gallery, otherGalleries }: GalleryShowProps) {
    return (
        <PublicPage
            title={gallery.title}
            eyebrow="Gallery"
            description={gallery.description ?? 'An image gallery from the Gombe State L-PRES project.'}
            breadcrumbs={[{ title: 'Media', href: '/media' }, { title: 'Gallery', href: '' }]}
        >
            <article className="bg-white">
                <div className="mb-2 flex flex-wrap items-center gap-x-6 gap-y-2">
                    {gallery.images.length > 0 && (
                        <p className="text-lpres-green inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase">
                            <Images aria-hidden="true" className="size-4" />
                            {gallery.images.length} {gallery.images.length === 1 ? 'image' : 'images'}
                        </p>
                    )}
                    {gallery.seenOnLabel && gallery.seenOnHref && (
                        <Link
                            href={gallery.seenOnHref}
                            className="text-lpres-green inline-flex items-center gap-1.5 text-xs font-extrabold tracking-[1px] uppercase hover:underline"
                        >
                            <MapPin aria-hidden="true" className="size-4" />
                            {gallery.seenOnLabel}
                        </Link>
                    )}
                </div>

                <h2 className="text-lpres-deep mt-2 text-[34px] leading-[44px] font-extrabold tracking-[-0.4px]">{gallery.title}</h2>

                {gallery.description && (
                    <div className="mt-5 max-w-[820px]">
                        {gallery.description
                            .split(/\r?\n\r?\n|\n{2,}/)
                            .filter((part) => part.trim().length > 0)
                            .map((paragraph, index) => (
                                <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                    {paragraph}
                                </p>
                            ))}
                    </div>
                )}

                <div className="mt-10">
                    {gallery.images.length > 0 ? (
                        <ImageGallery items={gallery.images} />
                    ) : (
                        <EmptyState message="No images have been added to this gallery yet." />
                    )}
                </div>
            </article>

            {otherGalleries.length > 0 && (
                <section aria-labelledby="other-galleries" className="mt-14">
                    <h3 id="other-galleries" className="text-lpres-deep text-xl font-extrabold">
                        Other Galleries
                    </h3>
                    <ul className="mt-5 list-none divide-y divide-[#e1e8ea] border-y border-[#e1e8ea]">
                        {otherGalleries.map((item) => (
                            <li key={item.href} className="py-4">
                                <Link href={item.href} className="group block no-underline">
                                    <span className="group-hover:text-lpres-green text-base leading-6 font-bold text-lpres-deep transition-colors">
                                        {item.title}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </PublicPage>
    );
}
