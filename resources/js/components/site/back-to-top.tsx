import { ChevronUp } from 'lucide-react';

import { useEffect, useState, type MouseEvent } from 'react';

/**
 * Shared back-to-top control. Hidden until the user is two viewport heights
 * deep, fixed bottom-right, respects `prefers-reduced-motion` and uses the
 * same html scroll helper as the sticky header. Rendered once per page by
 * the public layout (and by the homepage), never twice.
 */
export default function BackToTop() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const update = () => {
            const scrolled = window.scrollY || document.documentElement.scrollTop;
            setVisible(scrolled > window.innerHeight * 2);
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
        };
    }, []);

    if (!visible) {
        return null;
    }

    const handleClick = (event: MouseEvent<HTMLButtonElement>) => {
        event.preventDefault();

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

        window.scrollTo({
            top: 0,
            behavior: reduced ? 'auto' : 'smooth',
        });

        // Move focus back to the page banner so keyboard users are on top too.
        document.getElementById('page-banner')?.focus({ preventScroll: true });
    };

    return (
        <button
            type="button"
            aria-label="Back to top"
            onClick={handleClick}
            className="bg-lpres-deep text-white hover:bg-lpres-green fixed right-4 bottom-4 z-50 flex h-[46px] w-[46px] items-center justify-center rounded-full shadow-[0_2px_10px_rgba(0,59,73,0.35)] transition-colors duration-300 focus-visible:ring-2 focus-visible:ring-lpres-green focus-visible:ring-offset-2 focus-visible:outline-none md:right-6 md:bottom-6"
        >
            <ChevronUp aria-hidden="true" className="size-5" strokeWidth={2.4} />
        </button>
    );
}
