import { Link } from '@inertiajs/react';

import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationProps<T> {
    paginator: {
        data: T[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}

/** Server-side pagination strip for Inertia paginator props. */
export default function Pagination<T>({ paginator }: PaginationProps<T>) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className="mt-11 flex items-center justify-between">
            <p className="text-sm text-[#838e91]">
                Page {paginator.current_page} of {paginator.last_page}
            </p>
            <div className="flex gap-2.5">
                <Link
                    href={paginator.prev_page_url ?? '#'}
                    aria-disabled={!paginator.prev_page_url}
                    aria-label="Previous page"
                    className={`bg-white flex size-11 items-center justify-center border border-[#e1e8ea] transition-colors ${
                        paginator.prev_page_url ? 'hover:bg-lpres-deep hover:text-white hover:border-lpres-deep' : 'pointer-events-none opacity-40'
                    }`}
                >
                    <ChevronLeft aria-hidden="true" className="size-5" />
                </Link>
                <Link
                    href={paginator.next_page_url ?? '#'}
                    aria-disabled={!paginator.next_page_url}
                    aria-label="Next page"
                    className={`bg-white flex size-11 items-center justify-center border border-[#e1e8ea] transition-colors ${
                        paginator.next_page_url ? 'hover:bg-lpres-deep hover:text-white hover:border-lpres-deep' : 'pointer-events-none opacity-40'
                    }`}
                >
                    <ChevronRight aria-hidden="true" className="size-5" />
                </Link>
            </div>
        </nav>
    );
}
