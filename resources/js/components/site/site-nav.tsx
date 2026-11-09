import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';

import { siteNavigation } from '@/config/site';
import { cn } from '@/lib/utils';

/**
 * Desktop navigation row: every top-level public link is directly visible,
 * with hover/focus dropdowns for logically grouped pages (About Us, Value
 * Chain Clusters). Rendered from the `xl` breakpoint so the longest row
 * (ten items) always fits beside the 253px logo; smaller widths use the
 * hamburger menu. Keyboard users can reach and open the submenus with focus.
 */
export default function SiteNav({ className }: { className?: string }) {
    const { url } = usePage();
    const pathname = url.split('?')[0];
    const [openLabel, setOpenLabel] = useState<string | null>(null);

    const isActive = (href: string) =>
        href === '/' ? pathname === '/' : pathname === href || pathname.startsWith(href + '/');

    return (
        <nav aria-label="Primary" className={className}>
            <ul className="flex list-none items-center gap-3 2xl:gap-4" onMouseLeave={() => setOpenLabel(null)}>
                {siteNavigation.map((item) => {
                    const hasChildren = Boolean(item.children?.length);
                    const isCurrent = isActive(item.href);

                    return (
                        <li key={item.label} className="relative">
                            <div className="flex items-center">
                                <Link
                                    href={item.href}
                                    aria-current={isCurrent ? 'page' : undefined}
                                    aria-haspopup={hasChildren ? 'true' : undefined}
                                    className={cn(
                                        'max-[1439px]:text-[13px] group inline-flex items-center gap-1 py-2 text-sm leading-[52px] font-bold tracking-[0.3px] whitespace-nowrap uppercase transition-colors focus-visible:outline-none',
                                        isCurrent
                                            ? 'text-lpres-green'
                                            : 'text-lpres-deep hover:text-lpres-green',
                                    )}
                                >
                                    {item.label}
                                </Link>
                                {hasChildren && (
                                    <button
                                        type="button"
                                        aria-expanded={openLabel === item.label}
                                        aria-label={`${isCurrent ? 'Hide' : 'Show'} ${item.label} submenu`}
                                        onClick={() => setOpenLabel(openLabel === item.label ? null : item.label)}
                                        onMouseEnter={() => setOpenLabel(item.label)}
                                        onFocus={() => setOpenLabel(item.label)}
                                        className="p-1 text-inherit focus-visible:outline-none"
                                    >
                                        <ChevronDown
                                            aria-hidden="true"
                                            className={cn(
                                                'size-3.5 transition-transform duration-200',
                                                openLabel === item.label && 'rotate-180',
                                            )}
                                            strokeWidth={2.5}
                                        />
                                    </button>
                                )}
                            </div>

                            {/* Active underline, mirroring the hover state. */}
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute bottom-[6px] left-0 h-0.5 bg-lpres-green transition-all duration-300',
                                    isCurrent ? 'w-full' : 'w-0 group-hover:w-full',
                                )}
                            />

                            {hasChildren && openLabel === item.label && (
                                <ul className="absolute top-full left-1/2 z-50 -translate-x-1/2 rounded-sm bg-white py-2 shadow-[0_18px_38px_-14px_rgba(0,59,73,0.4)] ring-1 ring-[#e1e8ea]">
                                    {item.children?.map((child) => (
                                        <li key={child.label}>
                                            <Link
                                                href={child.href}
                                                onClick={() => setOpenLabel(null)}
                                                className="hover:bg-lpres-mist focus-visible:bg-lpres-mist block min-w-[210px] px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-lpres-deep no-underline hover:text-lpres-green"
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
    );
}
