import ImageGallery from '@/components/site/image-gallery';
import PublicPage from '@/layouts/public-page';
import AssociatedMedia from '@/components/site/associated-media';

interface EventShowProps {
    event: {
        title: string;
        description: string | null;
        image: string | null;
        gallery: string[];
        startsAt: string | null;
        endsAt: string | null;
        location: string | null;
    };
    mediaGalleries: { title: string; href: string; imageCount: number; cover: string | null }[];
    mediaVideos: { title: string; caption: string | null; videoUrl: string; thumbnail: string | null }[];
    mediaImages: { title: string; caption: string | null; src: string | null }[];
}

export default function EventShow({ event, mediaGalleries, mediaVideos, mediaImages }: EventShowProps) {
    return (
        <PublicPage
            title={event.title}
            description={event.location ?? undefined}
            breadcrumbs={[{ title: 'Events', href: '/events' }, { title: 'Event', href: '' }]}
        >
            <article className="bg-white">
                {event.image && (
                    <img src={event.image} alt={event.title} width={1200} height={675} className="mb-8 block w-full object-cover" loading="lazy" decoding="async" />
                )}

                {(event.startsAt || event.location) && (
                    <ul className="mt-2 list-none space-y-1 text-sm text-[#838e91]">
                        {event.startsAt && (
                            <li>
                                <strong className="font-bold text-lpres-deep">Dates:</strong>{' '}
                                {event.endsAt && event.endsAt !== event.startsAt ? `${event.startsAt} – ${event.endsAt}` : event.startsAt}
                            </li>
                        )}
                        {event.location && (
                            <li>
                                <strong className="font-bold text-lpres-deep">Location:</strong> {event.location}
                            </li>
                        )}
                    </ul>
                )}

                <h2 className="text-lpres-deep mt-4 text-[34px] leading-[44px] font-extrabold tracking-[-0.4px]">{event.title}</h2>

                {event.description ? (
                    <div className="mt-6 max-w-[820px]">
                        {event.description
                            .split(/\r?\n\r?\n|\n{2,}/)
                            .filter((part) => part.trim().length > 0)
                            .map((paragraph, index) => (
                                <p key={index} className="mb-5 text-base leading-[30px] text-[#4b5e65] last:mb-0">
                                    {paragraph}
                                </p>
                            ))}
                    </div>
                ) : null}

                {event.gallery.length > 0 && (
                    <section aria-label="Event photo gallery" className="mt-10">
                        <h3 className="text-lpres-deep text-xl font-extrabold">Event Gallery</h3>
                        <div className="mt-5">
                            <ImageGallery
                                items={event.gallery.map((src, index) => ({
                                    src,
                                    alt: `${event.title} — gallery image ${index + 1}`,
                                }))}
                            />
                        </div>
                    </section>
                )}

                <AssociatedMedia galleries={mediaGalleries} videos={mediaVideos} images={mediaImages} />
            </article>
        </PublicPage>
    );
}
