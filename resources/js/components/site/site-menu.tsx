import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, X } from 'lucide-react';
import { useState } from 'react';

import { SheetClose, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { navigationWithClusters } from '@/config/site';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';

interface SiteMenuProps {
    onNavigate: () => void;
}

export default function SiteMenu({ onNavigate }: SiteMenuProps) {
    const { url, props } = usePage<SharedData>();
    const pathname = url.split('?')[0];
    const [expandedItems, setExpandedItems] = useState<Record<string, boolean>>({});
    // Clusters come from Laravel, so admin-created clusters appear here.
    const navigation = navigationWithClusters(props.navClusters ?? []);

    const toggleSubmenu = (label: string) => {
        setExpandedItems((current) => ({ ...current, [label]: !current[label] }));
    };

    return (
        <SheetContent
            side="left"
            showCloseButton={false}
            overlayClassName="bg-lpres-ink/90 motion-reduce:animate-none"
            className="bg-lpres-deep w-[300px] max-w-[85vw] gap-0 border-0 p-0 text-white shadow-none data-[state=closed]:duration-300 data-[state=open]:duration-500 motion-reduce:animate-none motion-reduce:transition-none sm:max-w-[300px]"
            aria-describedby="site-menu-description"
        >
            <SheetTitle className="sr-only">Main navigation</SheetTitle>
            <SheetDescription id="site-menu-description" className="sr-only">
                Use this menu to navigate the Gombe State L-PRES website. Press Escape to close it.
            </SheetDescription>

            <div className="relative flex h-full min-h-0 flex-col overflow-y-auto px-[15px] pt-[30px] pb-10">
                <SheetClose asChild>
                    <button
                        type="button"
                        aria-label="Close navigation menu"
                        className="hover:text-lpres-green focus-visible:ring-offset-lpres-deep absolute top-[10px] right-[22px] z-10 flex size-11 items-center justify-center rounded-sm text-white transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <X aria-hidden="true" className="size-6" strokeWidth={1.6} />
                    </button>
                </SheetClose>

                <Link
                    href="/"
                    onClick={onNavigate}
                    aria-label="Gombe State L-PRES home"
                    className="focus-visible:ring-offset-lpres-deep mb-5 block w-[100px] self-start focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <img
                        src="/images/lpres/lpres-logo-compact.jpg"
                        alt="Gombe L-PRES"
                        width={100}
                        height={50}
                        className="block h-[50px] w-[100px] bg-white object-contain"
                    />
                </Link>

                <nav aria-label="Main navigation">
                    <ul className="list-none">
                        {navigation.map((item) => {
                            const isCurrent = item.href === '/' ? pathname === '/' : pathname === item.href;
                            const hasChildren = Boolean(item.children?.length);
                            const isExpanded = Boolean(expandedItems[item.label]);
                            const submenuId = `site-menu-${item.label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;

                            return (
                                <li key={item.label} className="border-b border-white/15">
                                    <div className="flex min-h-[45px] items-center">
                                        <Link
                                            href={item.href}
                                            onClick={onNavigate}
                                            aria-current={isCurrent ? 'page' : undefined}
                                            className={cn(
                                                'hover:text-lpres-green focus-visible:ring-offset-lpres-deep flex min-h-[45px] flex-1 items-center text-base leading-[45px] font-semibold text-white transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none',
                                                hasChildren && 'pr-4',
                                            )}
                                        >
                                            {item.label}
                                        </Link>

                                        {hasChildren && (
                                            <button
                                                type="button"
                                                aria-expanded={isExpanded}
                                                aria-controls={submenuId}
                                                aria-label={`${isExpanded ? 'Hide' : 'Show'} ${item.label} submenu`}
                                                onClick={() => toggleSubmenu(item.label)}
                                                className="hover:bg-lpres-mist focus-visible:ring-lpres-green focus-visible:ring-offset-lpres-deep flex size-[30px] shrink-0 items-center justify-center bg-white text-black transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                            >
                                                <ChevronRight
                                                    aria-hidden="true"
                                                    className={cn(
                                                        'size-3.5 transition-transform duration-200 motion-reduce:transition-none',
                                                        isExpanded && 'rotate-90',
                                                    )}
                                                    strokeWidth={2.5}
                                                />
                                            </button>
                                        )}
                                    </div>

                                    {hasChildren && isExpanded && (
                                        <ul id={submenuId} className="list-none pt-1 pb-4">
                                            {item.children?.map((child) => (
                                                <li key={child.label}>
                                                    <Link
                                                        href={child.href}
                                                        onClick={onNavigate}
                                                        className="focus-visible:ring-offset-lpres-deep flex min-h-9 items-center py-1.5 pl-3 text-sm leading-5 font-medium text-white/80 transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    >
                                                        {child.label}
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            </div>
        </SheetContent>
    );
}
