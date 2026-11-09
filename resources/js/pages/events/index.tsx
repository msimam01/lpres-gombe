import PublicPage from '@/layouts/public-page';
import EmptyState from '@/components/site/empty-state';
import Pagination from '@/components/site/pagination';
import { Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';

export interface EventCard {
    id: number;
    title: string;
    href: string;
    excerpt: string | null;
    image: string | null;
    startsAt: string | null;
    endsAt: string | null;
    location: string | null;
}

export interface EventsPaginator {
    data: EventCard[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface EventsIndexProps {
    events: EventsPaginator;
}

/**
 * Events listing backed by the admin-managed events module. Hidden when there
 * is nothing scheduled yet — no fabricated entries are ever shown.
 */
export default function EventsIndex({ events }: EventsIndexProps) {
    return (
        <PublicPage title="Events" eyebrow="Mark Your Calendar" description="Events, trainings and engagements organised by the Gombe State L-PRES project.">
            {events.data.length > 0 ? (
                <>
                    <ul className="grid list-none gap-8 sm:grid-cols-2">
                        {events.data.map((event) => (
                            <li key={event.id}>
                                <Link
                                    href={event.href}
                                    className="group block h-full bg-[#f1f5f6] no-underline transition-[transform,box-shadow] duration-500 hover:-translate-y-1.5 hover:shadow-[0_24px_44px_-20px_rgba(0,59,73,0.35)]"
                                >
                                    <div className="overflow-hidden">
                                        {event.image ? (
                                            <img
                                                src={event.image}
                                                alt={event.title}
                                                width={465}
                                                height={375}
                                                loading="lazy"
                                                decoding="async"
                                                className="block aspect-[465/375] w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                                            />
                                        ) : (
                                            <div aria-hidden="true" className="block aspect-[465/375] w-full bg-lpres-deep" />
                                        )}
                                    </div>

                                    <div className="px-[30px] py-6 text-center">
                                        {event.startsAt && (
                                            <p className="text-lpres-green inline-flex items-center gap-2 text-xs font-extrabold tracking-[1px] uppercase">
                                                <CalendarDays aria-hidden="true" className="size-4" />
                                                {event.endsAt && event.endsAt !== event.startsAt ? `${event.startsAt} – ${event.endsAt}` : event.startsAt}
                                            </p>
                                        )}
                                        <h2 className="mt-1 text-xl leading-8 font-extrabold text-lpres-deep transition-colors duration-500 group-hover:text-lpres-green">
                                            {event.title}
                                        </h2>
                                        {event.location && <p className="mt-1 text-sm text-[#838e91]">{event.location}</p>}
                                        {event.excerpt && (
                                            <p className="mt-2 line-clamp-3 text-base leading-[27px] text-[#838e91]">{event.excerpt}</p>
                                        )}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <Pagination paginator={events} />
                </>
            ) : (
                <EmptyState message="No events have been scheduled yet. Upcoming engagements added in the admin panel will appear here." />
            )}
        </PublicPage>
    );
}
