import { Link } from '@inertiajs/react';
import { ArrowRight, CalendarDays, MapPin } from 'lucide-react';

import SectionHeading from '@/components/site/section-heading';

export interface HomeEventItem {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    startsAt: string | null;
    endsAt: string | null;
    location: string | null;
}

/**
 * Homepage Events section: the next admin-managed published events (capped
 * by config; default three) with their dates and locations. White bordered
 * cards that lift with a deep-teal shadow on hover while the photograph
 * zooms gently; the schedule reads with a calendar glyph and the location
 * with a map pin. The section is removed from the page entirely when nothing
 * is scheduled — the empty handling lives on the /events archive page.
 */
export default function HomeEvents({ events }: { events: HomeEventItem[] }) {
    if (events.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="home-events-heading"
            className="bg-[#f2f5f6] pt-[120px] pb-[120px] max-[1199px]:pt-[70px] max-[1199px]:pb-[70px]"
        >
            <div className="mx-auto w-full max-w-[1200px] px-5 sm:px-6 lg:px-0">
                <SectionHeading eyebrow="Mark Your Calendar" title="Upcoming Events" id="home-events-heading" />

                <ul className="grid list-none gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    {events.map((event) => {
                        const hasDistinctEnd = Boolean(event.endsAt && event.endsAt !== event.startsAt);

                        return (
                            <li key={event.id}>
                                <Link
                                    href={event.href}
                                    className="group focus-visible:outline-lpres-green block h-full border border-[#e1e8ea] bg-white no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:border-transparent hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)] focus-visible:outline-2 focus-visible:outline-offset-4 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                                >
                                    {event.image ? (
                                        <div className="relative overflow-hidden">
                                            <img
                                                src={event.image}
                                                alt=""
                                                width={465}
                                                height={375}
                                                loading="lazy"
                                                decoding="async"
                                                className="block aspect-[465/375] w-full object-cover transition-transform duration-700 group-hover:scale-[1.06] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                                            />
                                        </div>
                                    ) : (
                                        <div
                                            aria-hidden="true"
                                            className="bg-lpres-deep flex aspect-[465/375] w-full items-center justify-center"
                                        >
                                            <CalendarDays className="size-12 text-white/30" aria-hidden="true" />
                                        </div>
                                    )}

                                    <div className="px-[30px] py-7">
                                        <p className="text-lpres-green flex items-start gap-2 text-xs leading-5 font-extrabold tracking-[1px] uppercase">
                                            <CalendarDays aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
                                            <span>
                                                <span className="sr-only">Date: </span>
                                                {event.startsAt ?? 'To be scheduled'}
                                                {hasDistinctEnd && (
                                                    <>
                                                        <span aria-hidden="true"> – </span>
                                                        <span className="sr-only"> to </span>
                                                        {event.endsAt}
                                                    </>
                                                )}
                                            </span>
                                        </p>

                                        <h3 className="text-lpres-deep group-hover:text-lpres-green group-focus-visible:text-lpres-green mt-3 line-clamp-2 text-xl leading-8 font-extrabold transition-colors duration-500">
                                            {event.title}
                                        </h3>

                                        {event.location && (
                                            <p className="mt-2 flex items-start gap-1.5 text-sm leading-5 font-semibold text-[#6f7b7e]">
                                                <MapPin aria-hidden="true" className="text-lpres-green mt-0.5 size-4 shrink-0" />
                                                <span>
                                                    <span className="sr-only">Location: </span>
                                                    {event.location}
                                                </span>
                                            </p>
                                        )}

                                        {event.excerpt && (
                                            <p className="mt-3 line-clamp-3 border-t border-[#e1e8ea] pt-3 text-[15px] leading-[26px] text-[#6f7b7e]">
                                                {event.excerpt}
                                            </p>
                                        )}

                                        <span className="text-lpres-green mt-5 inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase opacity-0 transition-all duration-500 group-hover:opacity-100 group-focus-visible:opacity-100 [@media(hover:none)]:opacity-100">
                                            View details
                                            <ArrowRight aria-hidden="true" className="size-4" />
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        );
                    })}
                </ul>

                <div className="mt-12 text-center">
                    <Link
                        href="/events"
                        className="focus-visible:outline-lpres-deep group border-lpres-green text-lpres-deep hover:bg-lpres-green inline-flex h-[52px] items-center gap-3 border px-8 text-sm font-extrabold tracking-[0.5px] uppercase transition-colors duration-500 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-none"
                    >
                        View All Events
                        <ArrowRight aria-hidden="true" className="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
