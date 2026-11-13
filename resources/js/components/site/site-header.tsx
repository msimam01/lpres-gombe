import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import SiteMenu from '@/components/site/site-menu';
import SiteNav from '@/components/site/site-nav';
import { cn } from '@/lib/utils';
import { Sheet, SheetTrigger } from '@/components/ui/sheet';

/**
 * Shared public header, sticky on every page. The bar stays in normal flow
 * (no layout jump) and compacts from the tall 136px brand bar to a slim
 * 76px bar once the page scrolls, matching the original sticky behaviour.
 */
export default function SiteHeader() {
    const [menuOpen, setMenuOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const update = () => setScrolled(window.scrollY > 48);

        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => window.removeEventListener('scroll', update);
    }, []);

    return (
        <header className={`bg-lpres-mist sticky top-0 z-50 w-full shadow-sm transition-shadow duration-300 ${scrolled ? 'shadow-md' : ''}`}>
            <div className={`flex w-full items-center justify-between transition-[height] duration-300 ${scrolled ? 'h-[76px]' : 'h-[136px]'}`}>
                <Link
                    href="/"
                    aria-label="Gombe State L-PRES home"
                    className="focus-visible:ring-lpres-deep flex h-full w-[253px] max-w-[66vw] shrink-0 items-center justify-center bg-white pl-2 focus-visible:ring-2 focus-visible:ring-offset-0 focus-visible:outline-none"
                >
                    <img
                        src="/images/lpres/lpres-logo.jpeg"
                        alt="Gombe State Livestock Productivity and Resilience Support Project (L-PRES)"
                        width={1080}
                        height={618}
                        className={`block w-auto max-w-[calc(100%_-_30px)] object-contain transition-[height] duration-300 ${scrolled ? 'h-[44px]' : 'h-[70px]'}`}
                    />
                </Link>

                <SiteNav className="hidden pr-5 xl:flex" />

                <Sheet open={menuOpen} onOpenChange={setMenuOpen}>
                    <SheetTrigger asChild>
                        <button
                            type="button"
                            aria-label="Open navigation menu"
                            className={cn(
                                'text-lpres-deep hover:text-lpres-green focus-visible:ring-lpres-deep focus-visible:ring-offset-lpres-mist mr-2 flex size-11 shrink-0 items-center justify-center rounded-sm transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none xl:hidden',
                            )}
                        >
                            <span aria-hidden="true" className="flex w-5 flex-col gap-1">
                                <span className="block h-0.5 w-full bg-current" />
                                <span className="block h-0.5 w-full bg-current" />
                                <span className="block h-0.5 w-full bg-current" />
                            </span>
                        </button>
                    </SheetTrigger>

                    <SiteMenu onNavigate={() => setMenuOpen(false)} />
                </Sheet>
            </div>
        </header>
    );
}
